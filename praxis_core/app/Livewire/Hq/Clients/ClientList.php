<?php

namespace App\Livewire\Hq\Clients;

use App\Support\Clients\ClientAccess;
use App\Support\Clients\Clients;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * HQ › Clients (docs/CLIENTS.md): every active client, and past ones under their own filter. Each row shows the main
 * contact, how many contacts, the projects, what is still owed (Finance only) and which invoice details are missing;
 * the row opens the client's page, where those details are changed.
 */
#[Layout('components.layouts.hq')]
class ClientList extends Component
{
    #[Url(except: 'active')]
    public string $show = 'active';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(ClientAccess::canView(Auth::user()), 403);
        if (! isset(Clients::SHOW[$this->show])) {
            $this->show = 'active';
        }
    }

    public function showOnly(string $show): void
    {
        $this->show = isset(Clients::SHOW[$show]) ? $show : 'active';
    }

    public function clearSearch(): void
    {
        $this->search = '';
    }

    public function render()
    {
        $money = ClientAccess::canSeeMoney(Auth::user());
        $clients = Clients::rows($this->show, mb_substr($this->search, 0, 100));

        return view('livewire.hq.clients.list', [
            'clients' => $clients,
            'counts' => Clients::counts(),
            'canSeeMoney' => $money,
            'unpaid' => $money ? Clients::unpaid($clients->pluck('id')) : [],
        ])->title(__('clients.title'));
    }
}
