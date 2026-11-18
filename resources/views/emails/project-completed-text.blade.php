Project delivered: {{ $projectName }} ({{ $projectCode }})

Hello {{ $clientName }},

Your project is complete. Thank you for trusting {{ $workspaceName }} with it.

Started: {{ $startedOn }}
Planned delivery: {{ $plannedOn }}
Delivered: {{ $deliveredOn }}
Time taken: {{ $calendarDays }} {{ $calendarDays === 1 ? 'day' : 'days' }}
Steps completed: {{ $stepsDone }} of {{ $stepsTotal }}
@if ($verdict === 'early')
Result: delivered {{ $daysDifference }} {{ $daysDifference === 1 ? 'day' : 'days' }} ahead of schedule
@elseif ($verdict === 'late')
Result: delivered {{ $daysDifference }} {{ $daysDifference === 1 ? 'day' : 'days' }} after the planned date
@else
Result: delivered on schedule
@endif

@if ($portalUrl)
View the project summary: {{ $portalUrl }}

@endif
If you notice anything that needs attention, reply to this email and we will take care of it.

{{ $senderName }}
