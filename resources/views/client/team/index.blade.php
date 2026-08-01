<x-portal-layout title="Team" portalLabel="{{ $client->company_name }}" header="Team" :nav="\App\Support\PortalNav::client($client, 'team')">
    <form method="POST" action="{{ route('client.team.store', $client) }}" class="table-wrap mb-6 grid gap-3 p-5 md:grid-cols-4">
        @csrf
        <input name="name" class="input-field" placeholder="Name" required>
        <input type="email" name="email" class="input-field" placeholder="Email" required>
        <select name="role" class="input-field">
            @foreach($roles as $role)
                <option value="{{ $role->value }}">{{ $role->label() }}</option>
            @endforeach
        </select>
        <button class="btn-primary">Add member</button>
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
