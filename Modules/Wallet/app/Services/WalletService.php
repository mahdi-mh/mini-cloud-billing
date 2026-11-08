<?php

namespace Modules\Wallet\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use Modules\Clock\Services\Clock;
use Modules\Customers\Models\Customer;
use Modules\Wallet\Enums\LedgerEntryType;
use Modules\Wallet\Exceptions\IdempotencyConflict;
use Modules\Wallet\Models\LedgerEntry;

class WalletService
{
    public function __construct(private readonly Clock $clock) {}

    /** The balance is always the sum of the history, it is never stored. */
    public function balance(Customer|int $customer): int
    {
        $id = $customer instanceof Customer ? $customer->getKey() : $customer;

        return (int) LedgerEntry::where('customer_id', $id)->sum('amount');
    }

    public function topUp(Customer $customer, int $amount, string $idempotencyKey, ?string $description = null): LedgerEntry
    {
        return $this->record($customer->getKey(), LedgerEntryType::TopUp, $amount, $idempotencyKey, $description);
    }

    public function refund(Customer $customer, int $amount, string $idempotencyKey, ?string $description = null): LedgerEntry
    {
        return $this->record($customer->getKey(), LedgerEntryType::Refund, $amount, $idempotencyKey, $description);
    }

    /** $amount is positive here, the ledger stores it as money going out. */
    public function charge(int $customerId, int $amount, string $idempotencyKey, ?string $description = null, ?string $referenceType = null, ?int $referenceId = null): LedgerEntry
    {
        return $this->record($customerId, LedgerEntryType::Charge, $amount, $idempotencyKey, $description, $referenceType, $referenceId);
    }

    private function record(int $customerId, LedgerEntryType $type, int $amount, string $key, ?string $description, ?string $referenceType = null, ?int $referenceId = null): LedgerEntry
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be a positive number of minor units.');
        }

        $signed = $type === LedgerEntryType::Charge ? -$amount : $amount;

        try {
            return LedgerEntry::create([
                'customer_id' => $customerId,
                'type' => $type,
                'amount' => $signed,
                'idempotency_key' => $key,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_at' => $this->clock->now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // a retry of something we already did: hand back the original, move no money
            $existing = LedgerEntry::where('idempotency_key', $key)->firstOrFail();

            if ($existing->customer_id !== $customerId || $existing->type !== $type || $existing->amount !== $signed) {
                throw new IdempotencyConflict("Key [{$key}] was already used for a different movement.");
            }

            return $existing;
        }
    }
}
