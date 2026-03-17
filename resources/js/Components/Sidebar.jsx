import { Link, usePage } from '@inertiajs/react';

const navConfig = {
    admin: [
        { label: 'Dashboard', href: '/dashboard', icon: '◈' },
        { label: 'Usuários', href: '/admin/users', icon: '◎' },
        { label: 'Turmas', href: '/classes', icon: '⊞' },
    ],
    professor: [
        { label: 'Dashboard', href: '/dashboard', icon: '◈' },
        { label: 'Minhas Turmas', href: '/classes', icon: '⊞' },
    ],
    aluno: [
        { label: 'Dashboard', href: '/dashboard', icon: '◈' },
        { label: 'Minhas Turmas', href: '/classes', icon: '⊞' },
    ],
};

const roleLabels = { admin: 'Administrador', professor: 'Professor', aluno: 'Aluno' };
const roleBadgeColors = {
    admin: 'bg-rose-500/20 text-rose-300',
    professor: 'bg-sky-500/20 text-sky-300',
    aluno: 'bg-emerald-500/20 text-emerald-300',
};

export default function Sidebar() {
    const { auth } = usePage().props;
    const role = auth?.user?.role ?? 'aluno';
    const items = navConfig[role] ?? navConfig.aluno;
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

    return (
        <aside className="w-64 bg-slate-900 flex flex-col flex-shrink-0 border-r border-slate-800">
            {/* Logo */}
            <div className="px-5 py-5 border-b border-slate-800">
                <div className="flex items-center gap-3">
                    <div className="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <span className="text-white font-bold text-sm">T</span>
                    </div>
                    <div>
                        <p className="text-white font-semibold text-sm leading-tight">TKL Idiomas</p>
                        <p className="text-slate-500 text-xs">Sistema de Gestão</p>
                    </div>
                </div>
            </div>

            {/* Nav */}
            <nav className="flex-1 px-3 py-4 space-y-0.5">
                {items.map((item) => {
                    const isActive = currentPath === item.href || (item.href !== '/dashboard' && currentPath.startsWith(item.href));
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                                isActive
                                    ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/30'
                                    : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100'
                            }`}
                        >
                            <span className={`text-base transition-transform group-hover:scale-110`}>
                                {item.icon}
                            </span>
                            {item.label}
                        </Link>
                    );
                })}
            </nav>

            {/* User section */}
            <div className="p-3 border-t border-slate-800">
                <div className="flex items-center gap-3 px-2 py-2 rounded-xl hover:bg-slate-800 transition-colors group">
                    <div className="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">
                        {auth?.user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="text-slate-200 text-xs font-medium truncate">{auth?.user?.name}</p>
                        <span className={`inline-block mt-0.5 text-xs px-1.5 py-0.5 rounded-md font-medium ${roleBadgeColors[role] ?? 'bg-slate-700 text-slate-300'}`}>
                            {roleLabels[role] ?? role}
                        </span>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="text-slate-600 hover:text-slate-300 transition-colors text-lg leading-none opacity-0 group-hover:opacity-100"
                        title="Sair"
                    >
                        →
                    </Link>
                </div>
            </div>
        </aside>
    );
}
