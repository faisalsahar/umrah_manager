<?php

namespace App\Livewire\Concerns;

use Flux\Flux;
use Illuminate\Database\QueryException;

/**
 * A shared "are you sure — and can you do this sum" gate for deleting a catalog
 * row that other records might depend on. Kept generic on purpose: each page
 * decides what counts as a dependent (a count it passes to confirmDelete()) and
 * how to actually find/delete its own model (the callable passed to
 * guardedDelete()) — this trait only owns the confirm/cancel state and the math
 * check itself.
 */
trait GuardsDestructiveDeletes
{
    public ?int $deletingId = null;

    public int $deletingDependents = 0;

    public int $mathA = 0;

    public int $mathB = 0;

    public string $mathAnswer = '';

    /**
     * Open the confirmation modal for the given row. A dependents count of 0 means
     * a plain confirm; anything higher additionally requires solving the math
     * check below before the delete proceeds — the extra friction is deliberate
     * for a row other data still points at.
     */
    public function confirmDelete(int $id, int $dependentsCount = 0): void
    {
        $this->deletingId = $id;
        $this->deletingDependents = $dependentsCount;
        $this->mathA = random_int(2, 12);
        $this->mathB = random_int(2, 12);
        $this->mathAnswer = '';
        $this->resetErrorBag('mathAnswer');
    }

    public function cancelDelete(): void
    {
        $this->reset(['deletingId', 'deletingDependents', 'mathA', 'mathB', 'mathAnswer']);
    }

    /**
     * Runs $delete($id) after checking the math answer (only enforced when there
     * are dependents). Also catches a database-level rejection — e.g. a RESTRICT
     * foreign key such as movements still referencing a movement type — and
     * surfaces it as a message instead of a crash.
     */
    protected function guardedDelete(callable $delete): void
    {
        // Reachable if deleteConfirmed() is ever called without a prior
        // confirmDelete() — e.g. a stale or crafted request — in which case
        // there's nothing to act on, not a 500.
        if ($this->deletingId === null) {
            return;
        }

        if ($this->deletingDependents > 0 && (int) $this->mathAnswer !== $this->mathA + $this->mathB) {
            $this->addError('mathAnswer', __('That doesn\'t add up — try again.'));

            return;
        }

        try {
            $delete($this->deletingId);
        } catch (QueryException) {
            Flux::toast(text: __('Could not delete — it is still used elsewhere.'), variant: 'danger');
            $this->cancelDelete();

            return;
        }

        Flux::toast(text: __('Deleted.'), variant: 'success');
        $this->cancelDelete();
    }
}
