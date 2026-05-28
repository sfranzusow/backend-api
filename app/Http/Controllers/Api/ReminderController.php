<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReminderRequest;
use App\Http\Requests\Api\UpdateReminderRequest;
use App\Http\Resources\Api\ReminderResource;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Reminder;
use App\Models\RentalAgreement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ReminderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Reminder::class);

        $validated = $this->validateDashboardFilters($request);
        $perPage = (int) ($validated['per_page'] ?? 15);

        $reminders = $this->dashboardReminderQuery($request, $validated)
            ->with(['remindable', 'creator:id,name,email', 'assignee:id,name,email'])
            ->orderBy('due_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return ReminderResource::collection($reminders);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Reminder::class);

        $validated = $this->validateDashboardFilters($request, includeListFilters: false);
        $baseQuery = $this->dashboardReminderQuery($request, $validated);
        $displayStatuses = Reminder::displayStatuses();
        $byDisplayStatus = array_fill_keys($displayStatuses, 0);
        $byRemindableType = [];

        foreach (Reminder::remindableTypes() as $typeLabel => $typeClass) {
            $typeCounts = array_fill_keys($displayStatuses, 0);

            foreach ($displayStatuses as $displayStatus) {
                $count = (clone $baseQuery)
                    ->where('remindable_type', $typeClass)
                    ->withDisplayStatus($displayStatus)
                    ->count();

                $typeCounts[$displayStatus] = $count;
                $byDisplayStatus[$displayStatus] += $count;
            }

            $typeCounts['total'] = array_sum($typeCounts);
            $byRemindableType[$typeLabel] = $typeCounts;
        }

        return response()->json([
            'data' => [
                'total' => array_sum($byDisplayStatus),
                'by_display_status' => $byDisplayStatus,
                'by_remindable_type' => $byRemindableType,
            ],
        ]);
    }

    public function indexForDocument(Request $request, Document $document): JsonResponse
    {
        return $this->indexFor($request, $document);
    }

    public function storeForDocument(StoreReminderRequest $request, Document $document): JsonResponse
    {
        return $this->storeFor($request, $document);
    }

    public function indexForRentalAgreement(Request $request, RentalAgreement $rentalAgreement): JsonResponse
    {
        return $this->indexFor($request, $rentalAgreement);
    }

    public function storeForRentalAgreement(StoreReminderRequest $request, RentalAgreement $rentalAgreement): JsonResponse
    {
        return $this->storeFor($request, $rentalAgreement);
    }

    public function indexForPayment(Request $request, Payment $payment): JsonResponse
    {
        return $this->indexFor($request, $payment);
    }

    public function storeForPayment(StoreReminderRequest $request, Payment $payment): JsonResponse
    {
        return $this->storeFor($request, $payment);
    }

    public function update(UpdateReminderRequest $request, Reminder $reminder): JsonResponse
    {
        $validated = $request->validated();

        if (($validated['status'] ?? null) === Reminder::STATUS_DONE) {
            $validated['completed_at'] ??= now();
        } elseif (array_key_exists('status', $validated)) {
            $validated['completed_at'] ??= null;
        }

        $reminder->forceFill($validated)->save();
        $reminder->load(['remindable', 'creator:id,name,email', 'assignee:id,name,email']);

        return response()->json([
            'data' => new ReminderResource($reminder),
        ]);
    }

    public function destroy(Reminder $reminder): Response
    {
        $this->authorize('delete', $reminder);

        $reminder->delete();

        return response()->noContent();
    }

    private function indexFor(Request $request, Model $remindable): JsonResponse
    {
        $this->authorize('view', $remindable);

        $authUser = $request->user();
        $limitToAssignedTenant = $authUser?->hasRole(RoleName::Tenant->value) === true
            && ! $authUser->hasRole(RoleName::Landlord->value);

        $reminders = $remindable->reminders()
            ->with(['creator:id,name,email', 'assignee:id,name,email'])
            ->when($limitToAssignedTenant, function ($query) use ($authUser) {
                $query->where('assigned_to_id', $authUser->id);
            })
            ->get();

        $reminders->each->setRelation('remindable', $remindable);

        return response()->json([
            'data' => ReminderResource::collection($reminders),
        ]);
    }

    private function storeFor(StoreReminderRequest $request, Model $remindable): JsonResponse
    {
        $reminder = $remindable->reminders()->create([
            ...$request->validated(),
            'status' => Reminder::STATUS_PENDING,
            'created_by_id' => $request->user()?->id,
        ]);

        $reminder->setRelation('remindable', $remindable);
        $reminder->load(['creator:id,name,email', 'assignee:id,name,email']);

        return response()->json([
            'data' => new ReminderResource($reminder),
        ], Response::HTTP_CREATED);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dashboardReminderQuery(Request $request, array $filters): Builder
    {
        $authUser = $request->user();
        $remindableType = $filters['remindable_type'] ?? null;

        return Reminder::query()
            ->assignedVisibleTo($authUser)
            ->when(is_string($remindableType), function (Builder $query) use ($remindableType): void {
                $query->where('remindable_type', Reminder::remindableTypes()[$remindableType]);
            })
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($filters['display_status'] ?? null, function (Builder $query, string $displayStatus): void {
                $query->withDisplayStatus($displayStatus);
            })
            ->when($filters['due_from'] ?? null, function (Builder $query, string $dueFrom): void {
                $query->where('due_at', '>=', $dueFrom);
            })
            ->when($filters['due_until'] ?? null, function (Builder $query, string $dueUntil): void {
                $query->where('due_at', '<=', $dueUntil);
            })
            ->when($filters['remind_from'] ?? null, function (Builder $query, string $remindFrom): void {
                $query->where('remind_at', '>=', $remindFrom);
            })
            ->when($filters['remind_until'] ?? null, function (Builder $query, string $remindUntil): void {
                $query->where('remind_at', '<=', $remindUntil);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDashboardFilters(Request $request, bool $includeListFilters = true): array
    {
        $rules = [
            'remindable_type' => ['nullable', 'string', Rule::in(array_keys(Reminder::remindableTypes()))],
            'status' => ['nullable', 'string', Rule::in(Reminder::statuses())],
            'due_from' => ['nullable', 'date'],
            'due_until' => ['nullable', 'date'],
            'remind_from' => ['nullable', 'date'],
            'remind_until' => ['nullable', 'date'],
        ];

        if ($includeListFilters) {
            $rules['display_status'] = ['nullable', 'string', Rule::in(Reminder::displayStatuses())];
            $rules['per_page'] = ['nullable', 'integer', 'min:1', 'max:100'];
        }

        return $request->validate($rules);
    }
}
