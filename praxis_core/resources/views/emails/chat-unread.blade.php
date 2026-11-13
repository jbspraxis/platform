@php($key = $forClient ? 'client' : 'staff')
@php($first = $lines[0]['name'] ?? $client)
<x-mail.layout :preheader="__('emails.chat.' . $key . '_preheader', ['name' => $first, 'project' => $project])" :headline="__('emails.chat.' . $key . '_headline', ['client' => $client])">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ trans_choice('emails.chat.' . $key . '_intro', $count, ['count' => $count, 'project' => $project, 'client' => $client]) }}</x-mail.p>
    @foreach ($lines as $line)
        <x-mail.small>{{ __('emails.chat.at', ['name' => $line['name'], 'time' => $line['time']]) }}</x-mail.small>
        <x-mail.quote>{{ $line['text'] }}</x-mail.quote>
    @endforeach
    @if ($count > count($lines))
        <x-mail.small>{{ trans_choice('emails.chat.more', $count - count($lines), ['count' => $count - count($lines)]) }}</x-mail.small>
    @endif
    <x-mail.button :url="$url" :label="__('emails.chat.' . $key . '_button')" />
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.chat.' . $key . '_reason', ['project' => $project, 'client' => $client]) }}</x-slot:reason>
</x-mail.layout>
