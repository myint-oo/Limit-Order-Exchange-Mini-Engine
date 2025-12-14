<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * WalletService handles all balance mutations for users and assets.
 */
class WalletService
{
    /**
     * Lock USD balance for a buy order.
     * 
     * @return bool True if lock succeeded, false if insufficient balance
     */
    public function lockUsdBalance(User $user, string $amount): bool
    {
        $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
        
        if (bccomp($lockedUser->balance, $amount, 8) < 0) {
            return false;
        }

        $lockedUser->balance = bcsub($lockedUser->balance, $amount, 8);
        $lockedUser->save();

        return true;
    }

    /**
     * Unlock USD balance when cancelling a buy order.
     * 
     * @param string $amount Amount to unlock
     */
    public function unlockUsdBalance(User $user, string $amount): void
    {
        $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
        $lockedUser->balance = bcadd($lockedUser->balance, $amount, 8);
        $lockedUser->save();
    }

    /**
     * Lock asset for a sell order.
     * 
     * @return bool True if lock succeeded, false if insufficient balance
     */
    public function lockAsset(User $user, string $symbol, string $amount): bool
    {
        $asset = Asset::where('user_id', $user->id)
            ->where('symbol', $symbol)
            ->lockForUpdate()
            ->first();

        if (! $asset) {
            return false;
        }

        $available = bcsub($asset->amount, $asset->locked_amount, 8);
        
        if (bccomp($available, $amount, 8) < 0) {
            return false;
        }

        $asset->locked_amount = bcadd($asset->locked_amount, $amount, 8);
        $asset->save();

        return true;
    }

    /**
     * Unlock asset when cancelling a sell order.
     */
    public function unlockAsset(User $user, string $symbol, string $amount): void
    {
        $asset = Asset::where('user_id', $user->id)
            ->where('symbol', $symbol)
            ->lockForUpdate()
            ->first();

        if ($asset) {
            $asset->locked_amount = bcsub($asset->locked_amount, $amount, 8);
            $asset->save();
        }
    }

    /**
     * Transfer USD from buyer to seller during trade execution.
     * Commission is deducted from seller's proceeds.
     * 
     * @param string $usdVolume Total USD volume
     * @param string $feeUsd Commission to deduct from seller
     */
    public function transferUsd(User $buyer, User $seller, string $usdVolume, string $feeUsd): void
    {
        // !! Buyer's funds are already locked in the order

        // Seller receives USD minus fee
        $sellerReceives = bcsub($usdVolume, $feeUsd, 8);

        $lockedSeller = User::where('id', $seller->id)->lockForUpdate()->first();
        $lockedSeller->balance = bcadd($lockedSeller->balance, $sellerReceives, 8);
        $lockedSeller->save();
    }

    /**
     * Refund excess locked USD to buyer after trade execution.
     * This handles price improvement scenarios.
     * 
     * @param User $buyer
     * @param string $amount Amount to refund
     */
    public function refundExcessUsd(User $buyer, string $amount): void
    {
        if (bccomp($amount, '0', 8) <= 0) {
            return;
        }

        $lockedBuyer = User::where('id', $buyer->id)->lockForUpdate()->first();
        $lockedBuyer->balance = bcadd($lockedBuyer->balance, $amount, 8);
        $lockedBuyer->save();
    }

    /**
     * Transfer asset from seller to buyer during trade execution.
     * 
     * @param User $seller
     * @param User $buyer
     * @param string $symbol Asset symbol
     * @param string $amount Amount to transfer
     */
    public function transferAsset(User $seller, User $buyer, string $symbol, string $amount): void
    {
        // Reduce seller's locked amount and total amount
        $sellerAsset = Asset::where('user_id', $seller->id)
            ->where('symbol', $symbol)
            ->lockForUpdate()
            ->first();

        $sellerAsset->locked_amount = bcsub($sellerAsset->locked_amount, $amount, 8);
        $sellerAsset->amount = bcsub($sellerAsset->amount, $amount, 8);
        $sellerAsset->save();

        // Credit buyer's asset
        $buyerAsset = Asset::where('user_id', $buyer->id)
            ->where('symbol', $symbol)
            ->lockForUpdate()
            ->first();

        if ($buyerAsset) {
            $buyerAsset->amount = bcadd($buyerAsset->amount, $amount, 8);
            $buyerAsset->save();
        } else {
            Asset::create([
                'user_id' => $buyer->id,
                'symbol' => $symbol,
                'amount' => $amount,
                'locked_amount' => '0',
            ]);
        }
    }

    /**
     * Get user's available USD balance.
     */
    public function getAvailableUsdBalance(User $user): string
    {
        $freshUser = User::find($user->id);
        return $freshUser->balance;
    }

    /**
     * Get user's available asset balance.
     */
    public function getAvailableAssetBalance(User $user, string $symbol): string
    {
        $asset = Asset::where('user_id', $user->id)
            ->where('symbol', $symbol)
            ->first();

        if (!$asset) {
            return '0';
        }

        return bcsub($asset->amount, $asset->locked_amount, 8);
    }

    /**
     * Get or create asset for user.
     */
    public function getOrCreateAsset(User $user, string $symbol): Asset
    {
        return Asset::firstOrCreate(
            ['user_id' => $user->id, 'symbol' => $symbol],
            ['amount' => '0', 'locked_amount' => '0']
        );
    }
}
