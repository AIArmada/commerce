# Seating

Seating owns seat maps, sections, seats, holds and allocations. Use EnsureSeatHoldAction for hold creation, ConvertHoldsToAllocationsAction for conversion, EnsureSectionAllocationAction for capacity allocation, and ReleaseAllocationsAction for release. The allocator delegates to the canonical hold action. Always preserve owner scoping and transaction locking. Livewire selections are not reservations.
