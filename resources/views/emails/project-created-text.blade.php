New project: {{ $projectName }} ({{ $projectCode }})

Hello {{ $clientName }},

We have set up your project and planned the work from kickoff to delivery.

Starts: {{ $startDate }}
Expected delivery: {{ $dueDate }}
@if ($workingDays)
Planned effort: {{ $workingDays }} working days
@endif
Steps planned: {{ $stepCount }}

@foreach ($steps as $i => $step)
{{ $i + 1 }}. {{ $step['title'] }}@if ($step['due']) (due {{ $step['due'] }})@endif

@endforeach
@if ($stepCount > count($steps))
...and {{ $stepCount - count($steps) }} more.

@endif
@if ($portalUrl)
Follow it in your portal: {{ $portalUrl }}

@endif
Dates are our current plan. If anything we need from you is late, or the scope changes, we will tell you how it affects the delivery date.

{{ $senderName }}
