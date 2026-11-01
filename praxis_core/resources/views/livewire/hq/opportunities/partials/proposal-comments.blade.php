{{-- The client's comments on one section (ProposalsTab): who, when, what, and whether it was dealt with --}}
<ul class="pp-comments">
    @foreach ($list as $c)
        <li @class(['pp-comment', 'is-resolved' => $c->resolved_at]) wire:key="c-{{ $c->id }}">
            <div class="pp-comment-meta">
                <span>{{ __('proposals.comments.by', ['name' => $c->author_name, 'date' => $day($c->created_at)]) }}@if ($c->resolved_at) · {{ __('proposals.comments.resolved') }}@endif</span>
                @if ($resolvable)
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="resolve('{{ $c->id }}', {{ $c->resolved_at ? 'false' : 'true' }})">
                        {{ $c->resolved_at ? __('proposals.comments.reopen') : __('proposals.comments.resolve') }}
                    </button>
                @endif
            </div>
            <p>{{ $c->body }}</p>
        </li>
    @endforeach
</ul>
