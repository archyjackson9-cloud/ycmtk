<?php

namespace App\Enums;

/**
 * Inventory audit trail (TOR §9 StockMovement entity, §6.11 Inventory
 * reporting - stock movement history).
 */
enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';
    case Reserved = 'reserved';
    case ReservationReleased = 'reservation_released';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Stock In',
            self::Out => 'Stock Out',
            self::Adjustment => 'Adjustment',
            self::Reserved => 'Reserved',
            self::ReservationReleased => 'Reservation Released',
        };
    }
}
