<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $resume['name'] }} — Resume</title>
    <style>
        @page { margin: 36px 52px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; line-height: 1.38; color: #111827; }
        header { text-align: center; padding-bottom: 8px; border-bottom: 1.5px solid #111827; }
        h1 { font-size: 20px; letter-spacing: 0.06em; text-transform: uppercase; margin: 0 0 4px; }
        .contact { color: #374151; }
        .applying { margin-top: 6px; font-weight: bold; }
        h2 { font-size: 10.5px; letter-spacing: 0.08em; text-transform: uppercase; margin: 11px 0 4px; padding-bottom: 2px; border-bottom: 0.75px solid #9ca3af; }
        p { margin: 0; }
        table.row { width: 100%; border-collapse: collapse; }
        table.row td { padding: 0; vertical-align: top; }
        table.row td.when { text-align: right; white-space: nowrap; padding-left: 12px; color: #374151; }
        .entry { margin-bottom: 6px; page-break-inside: avoid; }
        .keep { page-break-inside: avoid; }
        .title { font-weight: bold; }
        .sub { color: #374151; }
        ul { margin: 3px 0 0 0; padding-left: 14px; }
        li { margin-bottom: 1px; }
        table.facts { border-collapse: collapse; }
        table.facts td { padding: 1px 0; vertical-align: top; }
        table.facts td.label { width: 110px; color: #374151; }
        table.references { width: 100%; border-collapse: collapse; }
        table.references td { width: 33%; padding-right: 10px; vertical-align: top; }
        .certify { margin-top: 16px; page-break-inside: avoid; }
        .signature { margin-top: 28px; width: 220px; margin-left: auto; text-align: center; border-top: 0.75px solid #111827; padding-top: 3px; }
    </style>
</head>
<body>
    <header>
        <h1>{{ $resume['name'] }}</h1>
        <div class="contact">
            {{ collect([$resume['address'], $resume['mobile'], $resume['email']])->filter()->implode('  ·  ') }}
        </div>
        @if ($resume['desired_position'])
            <div class="applying">Applying for: {{ $resume['desired_position'] }}</div>
        @endif
    </header>

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
                        <td><span class="title">{{ $job['position'] }}</span><br><span class="sub">{{ $job['company'] }}</span></td>
                        <td class="when">{{ $job['period'] }}</td>
                    </tr></table>
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
                        <td><span class="title">{{ $school['course'] }}</span><br><span class="sub">{{ $school['school'] }}</span></td>
                        <td class="when">{{ $school['year'] }}</td>
                    </tr></table>
                </div>
            @endforeach
        @endif
    @endforeach

    @if ($resume['trainings'])
        <h2>Trainings and Seminars</h2>
        @foreach ($resume['trainings'] as $training)
            <div class="entry">
                <span class="title">{{ $training['title'] }}</span>
                @if ($training['meta'])<br><span class="sub">{{ $training['meta'] }}</span>@endif
            </div>
        @endforeach
    @endif

    @if ($resume['certifications'])
        <h2>Licenses and Certifications</h2>
        @foreach ($resume['certifications'] as $certification)
            <div class="entry">
                <span class="title">{{ $certification['name'] }}</span>
                @if ($certification['meta'])<br><span class="sub">{{ $certification['meta'] }}</span>@endif
            </div>
        @endforeach
    @endif

    @if ($resume['skills'])
        <div class="keep">
            <h2>Skills</h2>
            <p>{{ implode(', ', $resume['skills']) }}</p>
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
                @foreach ($resume['personal'] as $label => $value)
                    <tr><td class="label">{{ $label }}</td><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        </div>
    @endif

    <div class="keep">
    <h2>Character References</h2>
    @if ($resume['references'])
        <table class="references"><tr>
            @foreach ($resume['references'] as $reference)
                <td>
                    <span class="title">{{ $reference['name'] }}</span>
                    @if (filled($reference['position'] ?? null))<br>{{ $reference['position'] }}@endif
                    @if (filled($reference['organization'] ?? null))<br><span class="sub">{{ $reference['organization'] }}</span>@endif
                    <br><span class="sub">{{ $reference['contact'] }}</span>
                </td>
            @endforeach
        </tr></table>
    @else
        <p>Available upon request.</p>
    @endif
    </div>

    @if ($resume['certify'])
        <div class="certify">
            <p>I hereby certify that the above information is true and correct to the best of my knowledge and belief.</p>
            <div class="signature">{{ $resume['name'] }}</div>
        </div>
    @endif
</body>
</html>
