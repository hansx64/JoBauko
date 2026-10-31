<?php

namespace App\Services\Recommendation;

final readonly class MatchResult
{
    /**
     * @param  int  $score  0-100
     * @param  list<string>  $reasons  why the opportunity fits
     * @param  list<string>  $gaps  what could make it fit better
     */
    public function __construct(
        public int $score,
        public array $reasons,
        public array $gaps,
    ) {}

    /**
     * @return array{score: int, reasons: list<string>, gaps: list<string>}
     */
    public function toArray(): array
    {
        return ['score' => $this->score, 'reasons' => $this->reasons, 'gaps' => $this->gaps];
    }
}
