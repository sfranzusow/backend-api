<?php

namespace App\Http\Resources\Api;

use App\Enums\RoleName;
use App\Models\Document;
use App\Models\Payment;
use App\Models\RentalAgreement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalAgreementResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isTenantView = $this->isTenantView($request);

        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'landlord_id' => $this->landlord_id,
            'tenant_id' => $this->tenant_id,
            'bank_account_id' => $this->bank_account_id,
            'date_from' => $this->date_from?->toDateString(),
            'date_to' => $this->date_to?->toDateString(),
            'rent_cold' => $this->rent_cold,
            'rent_warm' => $this->rent_warm,
            'service_charges' => $this->service_charges,
            'deposit' => $this->deposit,
            'currency' => $this->currency,
            'status' => $this->status,
            $this->mergeWhen(! $isTenantView, [
                'notes' => $this->notes,
            ]),
            'property' => PropertyResource::make($this->whenLoaded('property')),
            'landlord' => UserResource::make($this->whenLoaded('landlord')),
            'tenant' => UserResource::make($this->whenLoaded('tenant')),
            'bank_account' => BankAccountResource::make($this->whenLoaded('bankAccount')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'actions' => $this->actions($request),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function isTenantView(Request $request): bool
    {
        $authUser = $request->user();

        return $authUser?->hasRole(RoleName::Tenant->value) === true
            && ! $authUser->hasRole(RoleName::Landlord->value)
            && ! $authUser->hasRole(RoleName::Admin->value);
    }

    /**
     * @return array<string, bool>
     */
    private function actions(Request $request): array
    {
        $authUser = $request->user();
        $rentalAgreement = $this->resource;

        if (! $authUser instanceof User || ! $rentalAgreement instanceof RentalAgreement) {
            return [
                'update' => false,
                'delete' => false,
                'create_document' => false,
                'create_payment' => false,
            ];
        }

        return [
            'update' => $authUser->can('update', $rentalAgreement),
            'delete' => $authUser->can('delete', $rentalAgreement),
            'create_document' => $authUser->can('createForRentalAgreement', [Document::class, $rentalAgreement]),
            'create_payment' => $authUser->can('createForRentalAgreement', [Payment::class, $rentalAgreement]),
        ];
    }
}
