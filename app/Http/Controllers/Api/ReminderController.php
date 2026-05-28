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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReminderController extends Controller
{
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
}
