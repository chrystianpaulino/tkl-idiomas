import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Avatar from '@/Components/Avatar';
import Badge from '@/Components/Badge';
import DataTable from '@/Components/DataTable';
import { Head, Link, router, useForm } from '@inertiajs/react';

function PlusIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
    );
}

function SearchIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
    );
}

const roleTabs = [
    { value: '', label: 'Todos' },
    { value: 'admin', label: 'Admin' },
    { value: 'professor', label: 'Professor' },
    { value: 'aluno', label: 'Aluno' },
];

export default function UsersIndex({ users, filters }) {
    const { data, setData } = useForm({
        role: filters?.role ?? '',
        search: filters?.search ?? '',
    });

    function applyFilter(overrides = {}) {
        const params = { role: data.role, search: data.search, ...overrides };
        Object.keys(params).forEach((k) => { if (!params[k]) delete params[k]; });
        router.get('/admin/users', params, { preserveState: true, replace: true });
    }

    function handleRoleChange(role) {
        setData('role', role);
        applyFilter({ role });
    }

    const columns = [
        {
            key: 'name',
            label: 'Usuario',
            render: (row) => (
                <div className="flex items-center gap-3">
                    <Avatar name={row.name} size="sm" />
                    <div>
                        <p className="text-sm font-medium text-gray-900">{row.name}</p>
                        <p className="text-xs text-gray-500">{row.email}</p>
                    </div>
                </div>
            ),
        },
        {
            key: 'role',
            label: 'Papel',
            render: (row) => <Badge role={row.role} />,
        },
        {
            key: 'actions',
            label: '',
            render: (row) => (
                <div className="flex items-center gap-3 justify-end">
                    <Link href={`/admin/users/${row.id}`} className="text-sm font-medium text-indigo-600 hover:text-indigo-700 transition-colors">
                        Ver
                    </Link>
                    <button
                        type="button"
                        onClick={() => {
                            if (confirm('Tem certeza que deseja excluir este usuario?')) {
                                router.delete(`/admin/users/${row.id}`);
                            }
                        }}
                        className="text-sm text-rose-500 hover:text-rose-700 transition-colors"
                    >
                        Excluir
                    </button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Usuarios">
            <Head title="Usuarios" />
            <PageHeader title="Usuarios" subtitle="Gerenciar usuarios do sistema">
                <Link href="/admin/users/create" className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    <PlusIcon className="w-4 h-4" />
                    Novo Usuario
                </Link>
            </PageHeader>

            {/* Filters */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4 mb-6">
                {/* Role Tabs */}
                <div className="flex items-center gap-1 bg-gray-100 rounded-xl p-1">
                    {roleTabs.map((tab) => (
                        <button
                            key={tab.value}
                            type="button"
                            onClick={() => handleRoleChange(tab.value)}
                            className={`px-3.5 py-2 text-xs font-medium rounded-lg transition-colors ${
                                data.role === tab.value
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Search */}
                <div className="relative flex-1 max-w-md">
                    <SearchIcon className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Buscar por nome ou email..."
                        value={data.search}
                        onChange={(e) => setData('search', e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && applyFilter()}
                        className="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                    />
                </div>

                <button
                    type="button"
                    onClick={() => applyFilter()}
                    className="border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                >
                    Buscar
                </button>
            </div>

            <DataTable columns={columns} data={users?.data ?? []} emptyMessage="Nenhum usuario encontrado." />
        </AppLayout>
    );
}
