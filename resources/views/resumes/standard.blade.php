<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $resume['name'] }} — Resume</title>
    {{--
        Single-column chronological resume: easy for HR to scan and readable by
        applicant tracking systems. Rendered by dompdf, so layout uses tables
        rather than flexbox, and only the bundled DejaVu fonts.
    --}}
    <style>
        @page { margin: 34px 48px 36px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.6px; line-height: 1.36; color: #1f2937; }
        p { margin: 0; }

        .name { font-size: 22px; font-weight: bold; color: #0f172a; letter-spacing: 0.01em; margin: 0; }
        .headline { font-size: 11px; color: #166534; margin-top: 2px; }
        .contact { color: #4b5563; margin-top: 5px; }
        .contact .sep { color: #9ca3af; padding: 0 5px; }
        .bar { height: 2.5px; background: #166534; margin: 8px 0 0; }

        h2 {
            font-size: 9px; font-weight: bold; color: #166534; text-transform: uppercase; letter-spacing: 0.14em;
            margin: 11px 0 5px; padding-bottom: 2px; border-bottom: 0.75px solid #d1d5db;
        }

        table.row { width: 100%; border-collapse: collapse; }
        table.row td { padding: 0; vertical-align: top; }
        table.row td.when { text-align: right; white-space: nowrap; padding-left: 14px; color: #4b5563; font-size: 9px; }
        .entry { margin-bottom: 6px; page-break-inside: avoid; }
        .title { font-weight: bold; color: #111827; font-size: 10px; }
        .sub { color: #4b5563; font-style: italic; }
        .meta { color: #4b5563; }
        ul { margin: 3px 0 0; padding-left: 13px; }
        li { margin-bottom: 1px; }
        .keep { page-break-inside: avoid; }

        table.columns { width: 100%; border-collapse: collapse; }
        table.columns td { width: 33.33%; padding: 1px 10px 1px 0; vertical-align: top; }
        .dot { color: #166534; padding-right: 4px; }

        table.facts { width: 100%; border-collapse: collapse; }
        table.facts td { padding: 1.5px 0; vertical-align: top; width: 32%; }
        table.facts td.label { color: #6b7280; width: 18%; }

        .certify { margin-top: 12px; color: #4b5563; font-style: italic; page-break-inside: avoid; }
    </style>
</head>
<body>
    <p class="name">{{ $resume['name'] }}</p>
    @if ($resume['desired_position'])
        <div class="headline">{{ $resume['desired_position'] }}</div>
    @endif
    <div class="contact">{!! collect([$resume['address'], $resume['mobile'], $resume['email']])->filter()->map(fn ($item) => e($item))->implode('<span class="sep">|</span>') !!}</div>
    <div class="bar"></div>

    @if (filled($resume['objective']))
        <h2>Objective</h2>
        <p>{{ $resume['objective'] }}</p>
    @endif

    @php
        $sections = $resume['experience_first'] ? ['experience', 'education'] : ['education', 'experience'];
    @endphp

    @foreach ($sections as $section)
        @if ($section === 'experience' && $resume['experience'])
            <h2>Work Experience</h2>
            @foreach ($resume['experience'] as $job)
                <div class="entry">
                    <table class="row"><tr>
                        <td><span class="title">{{ $job['position'] }}</span></td>
                        <td class="when">{{ $job['period'] }}</td>
                    </tr></table>
                    <div class="sub">{{ $job['company'] }}</div>
                    @if ($job['duties'])
                        <ul>
                            @foreach ($job['duties'] as $duty)
                                <li>{{ $duty }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        @endif

        @if ($section === 'education' && $resume['education'])
            <h2>Education</h2>
            @foreach ($resume['education'] as $school)
                <div class="entry">
                    <table class="row"><tr>
                        <td><span class="title">{{ $school['course'] }}</span></td>
                        <td class="when">{{ $school['year'] }}</td>
                    </tr></table>
                    <div class="sub">{{ $school['school'] }}</div>
                </div>
            @endforeach
        @endif
    @endforeach

    @if ($resume['trainings'])
        <h2>Trainings and Seminars</h2>
        @foreach ($resume['trainings'] as $training)
            <div class="entry">
                <span class="title">{{ $training['title'] }}</span>
                @if ($training['meta'])<div class="meta">{{ $training['meta'] }}</div>@endif
            </div>
        @endforeach
    @endif

    @if ($resume['certifications'])
        <h2>Licenses and Certifications</h2>
        @foreach ($resume['certifications'] as $certification)
            <div class="entry">
                <span class="title">{{ $certification['name'] }}</span>
                @if ($certification['meta'])<div class="meta">{{ $certification['meta'] }}</div>@endif
            </div>
        @endforeach
    @endif

    @if ($resume['skills'])
        <div class="keep">
            <h2>Skills</h2>
            <table class="columns">
                @foreach (array_chunk($resume['skills'], 3) as $row)
                    <tr>
                        @foreach ($row as $skill)
                            <td><span class="dot">•</span>{{ $skill }}</td>
                        @endforeach
                        @for ($i = count($row); $i < 3; $i++)
                            <td></td>
                        @endfor
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if ($resume['languages'])
        <div class="keep">
            <h2>Languages</h2>
            <p>{{ implode(', ', $resume['languages']) }}</p>
        </div>
    @endif

    @if ($resume['personal'])
        <div class="keep">
            <h2>Personal Information</h2>
            <table class="facts">
                @foreach (array_chunk($resume['personal'], 2, true) as $pair)
                    <tr>
                        @foreach ($pair as $label => $value)
                            <td class="label">{{ $label }}</td><td>{{ $value }}</td>
                        @endforeach
                        @if (count($pair) < 2)<td></td><td></td>@endif
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <div class="keep">
        <h2>Character References</h2>
        @if ($resume['references'])
            <table class="columns"><tr>
                @foreach ($resume['references'] as $reference)
                    <td>
                        <span class="title">{{ $reference['name'] }}</span>
                        @if (filled($reference['position'] ?? null))<br>{{ $reference['position'] }}@endif
                        @if (filled($reference['organization'] ?? null))<br><span class="sub">{{ $reference['organization'] }}</span>@endif
                        <br><span class="meta">{{ $reference['contact'] }}</span>
                    </td>
                @endforeach
            </tr></table>
        @else
            <p>Available upon request.</p>
        @endif
    </div>

    @if ($resume['certify'])
        <p class="certify">I hereby certify that the above information is true and correct to the best of my knowledge and belief.</p>
    @endif
</body>
</html>
