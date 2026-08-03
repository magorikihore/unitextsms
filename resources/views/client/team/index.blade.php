<x-portal-layout title="Team" portalLabel="{{ $client->company_name }}" header="Team" :nav="\App\Support\PortalNav::client($client, 'team')">
    <form method="POST" action="{{ route('client.team.store', $client) }}" data-ajax class="table-wrap mb-6 grid gap-3 p-5 md:grid-cols-4" novalidate>
        @csrf
        <div data-form-error class="hidden md:col-span-4 rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
        <div>
            <input name="name" class="input-field" placeholder="Name">
            <p class="field-error hidden" data-error-for="name"></p>
        </div>
        <div>
            <input type="email" name="email" class="input-field" placeholder="Email">
            <p class="field-error hidden" data-error-for="email"></p>
        </div>
        <div>
            <select name="role" class="input-field">
                @foreach($roles as $role)
                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                @endforeach
            </select>
            <p class="field-error hidden" data-error-for="role"></p>
        </div>
        <button class="btn-primary" data-loading-text="Saving…">Add member</button>
    </form>

    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Active</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($client->users as $user)
                <tr>
                    <td class="px-4 py-3">{{ $user->name }}</td>
                    <td class="px-4 py-3">{{ $user->email }}</td>
                    <td class="px-4 py-3">{{ $user->pivot->role }}</td>
                    <td class="px-4 py-3">{{ $user->pivot->is_active ? 'yes' : 'no' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-portal-layout>
