import AppLayout from '@/Layouts/AppLayout';
import StatsCard from '@/Components/StatsCard';
import Avatar from '@/Components/Avatar';
import Badge from '@/Components/Badge';
import { usePage, Head, Link } from '@inertiajs/react';

function ProgressBar({ used, total, className = '' }) {
    const pct = total > 0 ? Math.min((used / total) * 100, 100) : 0;
    const remaining = total - used;
    const barColor = pct >= 75 ? 'bg-rose-500' : pct >= 50 ? 'bg-amber-500' : 'bg-emerald-500';

    return (
        <div className={className}>
            <div className="flex items-center justify-between mb-1.5">
                <span className="text-xs text-gray-500">{used} de {total} aulas usadas</span>
                <span className="text-xs font-semibold text-gray-700">{remaining} restantes</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-3">
                <div
                    className={`${barColor} h-3 rounded-full transition-all duration-500`}
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
}

/* ─── Icons (24x24 heroicons-style) ─── */
function UsersIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
    );
}

function AcademicCapIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15v-3.375m0 0a6.75 6.75 0 0110.5 0" />
        </svg>
    );
}

function BookOpenIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
        </svg>
    );
}

function CubeIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
        </svg>
    );
}

function PencilIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
        </svg>
    );
}

function CalendarIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
    );
}

function CheckCircleIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

function TargetIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 21a9 9 0 100-18 9 9 0 000 18z" />
            <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" />
            <path d="M12 12h.01" />
        </svg>
    );
}

function ExclamationTriangleIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
    );
}

function InformationCircleIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
    );
}

function XCircleIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

function CurrencyIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

/* ─── Admin Dashboard ─── */
function AdminDashboard({ stats }) {
    return (
        <div className="space-y-8">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <StatsCard
                    title="Total de Alunos"
                    value={stats?.total_students}
                    icon={<UsersIcon className="w-5 h-5" />}
                    iconBg="bg-indigo-50"
                    iconColor="text-indigo-600"
                />
                <StatsCard
                    title="Turmas Ativas"
                    value={stats?.total_classes}
                    icon={<BookOpenIcon className="w-5 h-5" />}
                    iconBg="bg-sky-50"
                    iconColor="text-sky-600"
                />
                <StatsCard
                    title="Pacotes Ativos"
                    value={stats?.active_packages}
                    icon={<CubeIcon className="w-5 h-5" />}
                    iconBg="bg-emerald-50"
                    iconColor="text-emerald-600"
                />
                <StatsCard
                    title="Aulas Hoje"
                    value={stats?.lessons_today ?? stats?.total_lessons}
                    icon={<CalendarIcon className="w-5 h-5" />}
                    iconBg="bg-amber-50"
                    iconColor="text-amber-600"
                />
            </div>

            {/* Payment summary */}
            {stats?.payment_summary && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <StatsCard
                        title="Receita Total"
                        value={new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(stats.payment_summary.total_revenue ?? 0)}
                        icon={<CurrencyIcon className="w-5 h-5" />}
                        iconBg="bg-emerald-50"
                        iconColor="text-emerald-600"
                    />
                    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 hover:shadow-md transition-shadow flex items-center justify-between">
                        <div>
                            <p className="text-3xl font-bold text-gray-900 tabular-nums">{stats.payment_summary.unpaid_count ?? 0}</p>
                            <p className="text-sm text-gray-500 mt-1">Pacotes Nao Pagos</p>
                        </div>
                        <Link
                            href={route('admin.payments.report')}
                            className="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                        >
                            Ver Relatorio
                        </Link>
                    </div>
                </div>
            )}

            {/* Recent Activity */}
            {stats?.recent_lessons?.length > 0 && (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div className="px-6 py-4 border-b border-gray-100">
                        <h3 className="text-sm font-semibold text-gray-900">Atividade Recente</h3>
                    </div>
                    <ul className="divide-y divide-gray-100">
                        {stats.recent_lessons.map((lesson) => (
                            <li key={lesson.id} className="px-6 py-4 flex items-center gap-4 hover:bg-gray-50/50 transition-colors">
                                <Avatar name={lesson.professor?.name ?? lesson.student?.name ?? 'A'} size="sm" />
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-gray-900 truncate">{lesson.title}</p>
                                    <p className="text-xs text-gray-500 mt-0.5">
                                        {lesson.student?.name} &middot; {lesson.class_name}
                                    </p>
                                </div>
                                <span className="text-xs text-gray-400 flex-shrink-0">
                                    {lesson.conducted_at ? new Date(lesson.conducted_at).toLocaleDateString('pt-BR') : '\u2014'}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

/* ─── Professor Dashboard ─── */
function ProfessorDashboard({ stats }) {
    const week = stats?.week_schedule ?? [];

    return (
        <div className="space-y-8">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <StatsCard
                    title="Minhas Turmas"
                    value={stats?.total_classes}
                    icon={<BookOpenIcon className="w-5 h-5" />}
                    iconBg="bg-indigo-50"
                    iconColor="text-indigo-600"
                />
                <StatsCard
                    title="Aulas Ministradas"
                    value={stats?.total_lessons_taught}
                    icon={<PencilIcon className="w-5 h-5" />}
                    iconBg="bg-sky-50"
                    iconColor="text-sky-600"
                />
                <StatsCard
                    title="Alunos Ativos"
                    value={stats?.active_students ?? '\u2014'}
                    icon={<UsersIcon className="w-5 h-5" />}
                    iconBg="bg-emerald-50"
                    iconColor="text-emerald-600"
                />
            </div>

            {/* Agenda da semana */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-gray-900">Agenda da semana</h3>
                    <Link href="/scheduled-lessons" className="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                        Ver todos
                    </Link>
                </div>
                {week.length === 0 ? (
                    <p className="px-6 py-8 text-sm text-gray-500 text-center">
                        Nenhuma aula agendada para os proximos 7 dias.
                    </p>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {week.map((sl) => {
                            const d = sl.scheduled_at ? new Date(sl.scheduled_at) : null;
                            return (
                                <li key={sl.id} className="px-6 py-4 flex items-center gap-4">
                                    <div className="w-12 h-12 rounded-xl bg-sky-50 text-sky-700 flex flex-col items-center justify-center flex-shrink-0">
                                        <span className="text-[10px] uppercase">
                                            {d ? d.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '') : ''}
                                        </span>
                                        <span className="text-xs font-bold leading-none">
                                            {d ? d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) : '\u2014'}
                                        </span>
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{sl.class_name ?? '\u2014'}</p>
                                        <p className="text-xs text-gray-500 mt-0.5">
                                            {d ? d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '\u2014'}
                                            {sl.duration_minutes && (<> &middot; {sl.duration_minutes} min</>)}
                                        </p>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* My Classes */}
                {stats?.classes?.length > 0 && (
                    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-gray-900">Minhas Turmas</h3>
                            <Link href="/classes" className="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                Ver todas
                            </Link>
                        </div>
                        <ul className="divide-y divide-gray-100">
                            {stats.classes.map((c) => {
                                const paymentStat = stats?.class_payment_stats?.find((s) => s.class_id === c.id);
                                return (
                                    <li key={c.id}>
                                        <Link href={`/classes/${c.id}`} className="px-6 py-4 flex items-center justify-between hover:bg-gray-50/50 transition-colors block">
                                            <div className="flex items-center gap-3">
                                                <div className="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                                                    <BookOpenIcon className="w-4 h-4" />
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium text-gray-900">{c.name}</p>
                                                    <p className="text-xs text-gray-500">
                                                        {c.students_count ?? 0} alunos
                                                        {paymentStat && (
                                                            <span className="ml-2 text-emerald-600">
                                                                {paymentStat.paid}/{paymentStat.total} com pacote pago
                                                            </span>
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                            <svg className="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 5l7 7-7 7" /></svg>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}

                {/* Recent Lessons */}
                {stats?.recent_lessons?.length > 0 && (
                    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                        <div className="px-6 py-4 border-b border-gray-100">
                            <h3 className="text-sm font-semibold text-gray-900">Aulas Recentes</h3>
                        </div>
                        <ul className="divide-y divide-gray-100">
                            {stats.recent_lessons.map((lesson) => (
                                <li key={lesson.id} className="px-6 py-4 flex items-center gap-4">
                                    <Avatar name={lesson.student?.name ?? 'A'} size="sm" />
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{lesson.title}</p>
                                        <p className="text-xs text-gray-500 mt-0.5">
                                            {lesson.student?.name} &middot; {lesson.class_name}
                                        </p>
                                    </div>
                                    <span className="text-xs text-gray-400 flex-shrink-0">
                                        {lesson.conducted_at ? new Date(lesson.conducted_at).toLocaleDateString('pt-BR') : '\u2014'}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>

            {/* Students Needing Package Renewal */}
            {stats?.studentsNeedingPackage?.length > 0 && (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div className="px-6 py-4 border-b border-rose-100 flex items-center gap-3">
                        <div className="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center flex-shrink-0">
                            <ExclamationTriangleIcon className="w-4 h-4" />
                        </div>
                        <h3 className="text-sm font-semibold text-rose-900">Alunos que precisam renovar</h3>
                    </div>
                    <ul className="divide-y divide-gray-100">
                        {stats.studentsNeedingPackage.map((item) => (
                            <li key={item.package_id} className="px-6 py-4 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <Avatar name={item.student_name} size="sm" />
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{item.student_name}</p>
                                        <p className="text-xs text-gray-500">{item.used_lessons} de {item.total_lessons} aulas usadas</p>
                                    </div>
                                </div>
                                <Badge
                                    label="Pacote esgotado"
                                    className="bg-rose-50 text-rose-700 border border-rose-200"
                                />
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Quick Actions */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h3 className="text-sm font-semibold text-gray-900 mb-4">Acesso Rapido</h3>
                <div className="flex flex-wrap gap-3">
                    <Link href="/classes" className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                        <BookOpenIcon className="w-4 h-4" />
                        Minhas Turmas
                    </Link>
                </div>
            </div>
        </div>
    );
}

/* ─── Warning Banner ─── */
function WarningBanner({ warning }) {
    if (!warning) return null;

    const config = {
        last_lesson: {
            bg: 'bg-amber-50 border border-amber-200 text-amber-800',
            icon: <ExclamationTriangleIcon className="w-5 h-5 text-amber-600 flex-shrink-0" />,
            message: 'Ultima aula -- proximo pagamento necessario',
        },
        exhausted: {
            bg: 'bg-rose-50 border border-rose-200 text-rose-800',
            icon: <XCircleIcon className="w-5 h-5 text-rose-600 flex-shrink-0" />,
            message: 'Pacote esgotado -- entre em contato com seu professor para renovar',
        },
        no_package: {
            bg: 'bg-sky-50 border border-sky-200 text-sky-800',
            icon: <InformationCircleIcon className="w-5 h-5 text-sky-600 flex-shrink-0" />,
            message: 'Voce ainda nao tem um pacote de aulas',
        },
    };

    const cfg = config[warning];
    if (!cfg) return null;

    return (
        <div className={`rounded-xl p-4 flex items-center gap-3 ${cfg.bg}`} role="alert">
            {cfg.icon}
            <p className="text-sm font-medium">{cfg.message}</p>
        </div>
    );
}

/* ─── Progress Card (Aluno) ─── */
function ProgressCard({ progress }) {
    if (!progress) return null;

    return (
        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 className="text-sm font-semibold text-gray-900 mb-5">Meu Progresso</h3>

            <div className="grid grid-cols-3 gap-4 mb-6">
                <div className="text-center">
                    <p className="text-2xl font-bold text-gray-900">{progress.lessonsCompleted}</p>
                    <p className="text-xs text-gray-500 mt-1">Aulas</p>
                </div>
                <div className="text-center">
                    <p className="text-2xl font-bold text-gray-900">{progress.hoursStudied}h</p>
                    <p className="text-xs text-gray-500 mt-1">Estudadas</p>
                </div>
                <div className="text-center">
                    <p className="text-2xl font-bold text-gray-900">{progress.currentStreak}</p>
                    <p className="text-xs text-gray-500 mt-1">Semanas</p>
                </div>
            </div>

            {progress.nextMilestone != null ? (
                <div>
                    <div className="flex items-center justify-between mb-1.5">
                        <span className="text-xs text-gray-500">
                            Proxima conquista: {progress.nextMilestone} aulas
                        </span>
                        <span className="text-xs font-semibold text-gray-700">
                            {progress.milestoneProgress}%
                        </span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-3">
                        <div
                            className="bg-indigo-600 h-3 rounded-full transition-all duration-500"
                            style={{ width: `${Math.min(progress.milestoneProgress, 100)}%` }}
                        />
                    </div>
                </div>
            ) : (
                <p className="text-sm text-gray-700 text-center">
                    {'🏆'} Voce atingiu todas as conquistas!
                </p>
            )}
        </div>
    );
}

/* ─── Aluno Dashboard ─── */
function NextLessonCard({ nextLesson }) {
    if (!nextLesson) {
        return (
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h3 className="text-sm font-semibold text-gray-900 mb-2">Proxima aula</h3>
                <p className="text-sm text-gray-500">
                    Nenhuma aula agendada no momento. Fale com seu professor para definir a agenda.
                </p>
            </div>
        );
    }

    const d = nextLesson.scheduled_at ? new Date(nextLesson.scheduled_at) : null;
    const dateStr = d ? d.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' }) : '—';
    const timeStr = d ? d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';

    return (
        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div className="flex items-start justify-between mb-4">
                <h3 className="text-sm font-semibold text-gray-900">Proxima aula</h3>
                <Link href="/scheduled-lessons" className="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                    Ver agenda
                </Link>
            </div>
            <div className="flex items-start gap-4">
                <div className="w-14 h-14 rounded-xl bg-indigo-50 text-indigo-700 flex flex-col items-center justify-center flex-shrink-0">
                    <CalendarIcon className="w-5 h-5" />
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-900 capitalize">{dateStr}</p>
                    <p className="text-sm text-gray-700 mt-0.5">
                        {timeStr}
                        {nextLesson.duration_minutes && (<> &middot; {nextLesson.duration_minutes} min</>)}
                    </p>
                    <p className="text-xs text-gray-500 mt-1">
                        {nextLesson.class_name ?? '—'}
                        {nextLesson.professor && (<> &middot; Prof. {nextLesson.professor}</>)}
                    </p>
                </div>
            </div>
        </div>
    );
}

function AlunoDashboard({ stats }) {
    const pkg = stats?.activePackage;
    const warning = stats?.warning;
    const summaryStats = stats?.stats;

    return (
        <div className="space-y-8">
            {/* Warning Banner */}
            <WarningBanner warning={warning} />

            {/* Proxima aula */}
            <NextLessonCard nextLesson={stats?.next_lesson} />

            {/* Stats Row */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <StatsCard
                    title="Aulas Realizadas"
                    value={summaryStats?.totalLessonsUsed ?? 0}
                    icon={<CheckCircleIcon className="w-5 h-5" />}
                    iconBg="bg-emerald-50"
                    iconColor="text-emerald-600"
                />
                <StatsCard
                    title="Aulas Restantes"
                    value={summaryStats?.remaining ?? 0}
                    icon={<TargetIcon className="w-5 h-5" />}
                    iconBg="bg-indigo-50"
                    iconColor="text-indigo-600"
                />
                <StatsCard
                    title="Turmas Matriculado"
                    value={stats?.enrolledClasses?.length ?? 0}
                    icon={<BookOpenIcon className="w-5 h-5" />}
                    iconBg="bg-sky-50"
                    iconColor="text-sky-600"
                />
            </div>

            {/* Active Package Card */}
            {pkg && (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-sm font-semibold text-gray-900">Pacote Ativo</h3>
                        <Badge
                            label={pkg.is_paid ? 'Pago' : 'Pendente'}
                            className={pkg.is_paid ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'}
                        />
                    </div>

                    <ProgressBar used={pkg.used_lessons} total={pkg.total_lessons} />

                    <p className="text-sm text-gray-700 mt-3">
                        {pkg.remaining} {pkg.remaining === 1 ? 'aula restante' : 'aulas restantes'} de {pkg.total_lessons}
                    </p>

                    <div className="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-xs text-gray-500">
                        {pkg.price != null && (
                            <span className="flex items-center gap-1.5">
                                <CurrencyIcon className="w-4 h-4 text-gray-400" />
                                Pacote: {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: pkg.currency ?? 'BRL' }).format(Number(pkg.price))} ({pkg.total_lessons} aulas)
                            </span>
                        )}
                        {pkg.expires_at && (
                            <span className="flex items-center gap-1.5">
                                <CalendarIcon className="w-4 h-4 text-gray-400" />
                                Valido ate {pkg.expires_at}
                            </span>
                        )}
                    </div>
                </div>
            )}

            {/* Progress Card */}
            <ProgressCard progress={stats?.progress} />

            {/* Payment History */}
            {stats?.payment_history?.length > 0 && (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div className="px-6 py-4 border-b border-gray-100">
                        <h3 className="text-sm font-semibold text-gray-900">Meus Pagamentos</h3>
                    </div>
                    <ul className="divide-y divide-gray-100">
                        {stats.payment_history.map((payment) => (
                            <li key={payment.id} className="px-6 py-4 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                                        <CurrencyIcon className="w-4 h-4" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">
                                            {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(payment.amount)}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {payment.total_lessons != null ? `${payment.total_lessons} aulas` : '—'} &middot; {payment.paid_at}
                                        </p>
                                    </div>
                                </div>
                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-50 text-gray-700 border border-gray-200">
                                    {{ pix: 'PIX', cash: 'Dinheiro', card: 'Cartao', transfer: 'Transferencia', other: 'Outro' }[payment.method] ?? payment.method}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Recent Lessons */}
                {stats?.recentLessons?.length > 0 && (
                    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                        <div className="px-6 py-4 border-b border-gray-100">
                            <h3 className="text-sm font-semibold text-gray-900">Aulas Recentes</h3>
                        </div>
                        <ul className="divide-y divide-gray-100">
                            {stats.recentLessons.map((lesson) => (
                                <li key={lesson.id} className="px-6 py-4 flex items-center gap-4">
                                    <span className="flex-shrink-0 w-12 text-center bg-indigo-50 text-indigo-700 text-xs font-semibold rounded-lg py-1.5 px-2">
                                        {lesson.conducted_at}
                                    </span>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{lesson.title}</p>
                                        <p className="text-xs text-gray-500 mt-0.5">Prof. {lesson.professor}</p>
                                    </div>
                                    <CheckCircleIcon className="w-5 h-5 text-emerald-500 flex-shrink-0" />
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* Enrolled Classes */}
                {stats?.enrolledClasses?.length > 0 && (
                    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                        <div className="px-6 py-4 border-b border-gray-100">
                            <h3 className="text-sm font-semibold text-gray-900">Minhas Turmas</h3>
                        </div>
                        <ul className="divide-y divide-gray-100">
                            {stats.enrolledClasses.map((c) => (
                                <li key={c.id}>
                                    <Link href={`/classes/${c.id}`} className="px-6 py-4 flex items-center justify-between hover:bg-gray-50/50 transition-colors block">
                                        <div className="flex items-center gap-3">
                                            <div className="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center flex-shrink-0">
                                                <BookOpenIcon className="w-4 h-4" />
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">{c.name}</p>
                                                <p className="text-xs text-gray-500">Prof. {c.professor}</p>
                                            </div>
                                        </div>
                                        <svg className="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 5l7 7-7 7" /></svg>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </div>
    );
}

/* ─── Main Dashboard ─── */
export default function Dashboard({ stats }) {
    const { auth } = usePage().props;
    const role = auth?.user?.role;
    const firstName = auth?.user?.name?.split(' ')[0] ?? '';

    const greetings = {
        school_admin: 'Painel Administrativo',
        admin: 'Painel Administrativo',
        professor: `Bem-vindo, ${firstName || 'Professor'}`,
        aluno: `Bem-vindo, ${firstName || 'Aluno'}`,
    };

    const subtitles = {
        school_admin: 'Visao geral da escola',
        professor: 'Aqui esta o resumo das suas atividades',
        aluno: 'Aqui esta o resumo do seu aprendizado',
    };

    const isAdmin = role === 'school_admin';

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="mb-8">
                <h1 className="text-2xl font-bold text-gray-900">{greetings[role] ?? 'Dashboard'}</h1>
                <p className="text-sm text-gray-500 mt-1">{subtitles[role] ?? ''}</p>
            </div>

            {isAdmin && <AdminDashboard stats={stats} />}
            {role === 'professor' && <ProfessorDashboard stats={stats} />}
            {role === 'aluno' && <AlunoDashboard stats={stats} />}
        </AppLayout>
    );
}
