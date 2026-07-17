<?php

namespace App\Traits;

use App\Services\Branches\ModuleBranchService;

trait CustomerComputed
{
    public function toFormattedArray()
    {
        $displayBalance = $this->displayBalanceForCustomerPage();

        return [
            'id' => $this->id,
            'image' => $this->user->profile_picture == 'default_avatar.png' ? '/images/default_avatar.png' : '/storage/uploads/images/' . $this->user->profile_picture,
            'name' => $this->customer_name,
            'details' => [
                'Urdu Title' => $this->urdu_title,
                'Category' => $this->category,
                'Balance' => \App\Support\Money::format($displayBalance),
            ],
            'person_name'=> $this->person_name,
            'phone_number'=> $this->phone_number,
            'user' => [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'status' => $this->user->status,
            ],
            'city' => $this->city->title,
            'date' => $this->date,
            'name_city' => $this->customer_name . ' | ' . $this->city->title,
            'data' => [
                'id' => $this->id,
                'customer_name' => $this->customer_name,
                'person_name' => $this->person_name,
                'urdu_title' => $this->urdu_title,
                'phone_number' => $this->phone_number,
                'category' => $this->category,
                'date' => $this->date,
                'city' => $this->city ? [
                    'id' => $this->city->id,
                    'title' => $this->city->title,
                    'short_title' => $this->city->short_title,
                ] : null,
                'user' => [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'status' => $this->user->status,
                ],
            ],
            'profile'=> true,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    private function displayBalanceForCustomerPage(): float|int
    {
        if (!request()->routeIs('customers.index')) {
            return $this->balance;
        }

        try {
            $branches = app(ModuleBranchService::class);
            if (!$branches->canShowSelector('customers') && !$branches->shouldFilterRecords('customers')) {
                return $this->balance;
            }

            $branchIds = $branches->selectedBranchIdsForModule('customers');
            if ($branchIds === [] && $branches->shouldFilterRecords('customers')) {
                $branchIds = array_values(array_filter([
                    $branches->selectedBranchIdForModule('customers'),
                ]));
            }

            if ($branchIds === []) {
                return $this->balance;
            }

            $mainBranchId = $branches->mainBranch()?->id;
            $includeNullBranchRecords = $mainBranchId
                && in_array((int) $mainBranchId, array_map('intval', $branchIds), true);

            return $this->calculateBalance(
                branchIds: $branchIds,
                includeNullBranchRecords: (bool) $includeNullBranchRecords,
            );
        } catch (\Throwable) {
            return $this->balance;
        }
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'username':
                return $query->whereHas('user', function ($q) use ($value) {
                    $q->where('username', 'like', '%' . $value . '%');
                });

            case 'city':
                return $query->whereHas('city', function ($q) use ($value) {
                    $q->where('id', $value);
                });

            case 'status':
                return $query->whereHas('user', function ($q) use ($value) {
                    $q->where('status', $value);
                });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
