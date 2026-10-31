<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The date range a report covers, inclusive of both days.
 */
final readonly class ReportPeriod
{
    public function __construct(public CarbonImmutable $from, public CarbonImmutable $to) {}

    /**
     * From the `from` and `to` query parameters; defaults to the current
     * year so far.
     */
    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : CarbonImmutable::today()->startOfYear();
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : CarbonImmutable::today();

        if ($from->gt($to)) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        return new self($from->startOfDay(), $to->endOfDay());
    }

    public function label(): string
    {
        return $this->from->format('M j, Y').' – '.$this->to->format('M j, Y');
    }

    /**
     * Calendar months the period touches, as Y-m keys, oldest first.
     *
     * @return list<string>
     */
    public function months(): array
    {
        $months = [];

        for ($month = $this->from->startOfMonth(); $month->lte($this->to); $month = $month->addMonth()) {
            $months[] = $month->format('Y-m');
        }

        return $months;
    }

    /**
     * @return array{from: string, to: string, label: string}
     */
    public function toArray(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString(), 'label' => $this->label()];
    }
}
