import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, useForm, Link } from '@inertiajs/react';

/**
 * Wave 9 — invite-driven user creation.
 *
 * The admin no longer types a password for the new user. Instead, the form
 * collects only identity (name, email, optional phone) and role; on submit,
 * the backend (InviteUserAction) creates the user in pending state and
 * dispatches an invite email. The recipient defines their own password by
 * following the link in that email.
 */
export default function UsersCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        role: 'aluno',
    });

    function submit(e) {
        e.preventDefault();
        post('/admin/users');
    }

    const fields = [
        { field: 'name', label: 'Nome Completo', type: 'text', placeholder: 'Ex: Maria Silva' },
        { field: 'email', label: 'Email', type: 'email', placeholder: 'email@exemplo.com' },
        { field: 'phone', label: 'Telefone (opcional)', type: 'text', placeholder: '(11) 91234-5678' },
    ];

    return (
        <AppLayout title="Novo Usuario">
            <Head title="Novo Usuario" />
            <PageHeader title="Novo Usuario" subtitle="Cadastrar um novo usuario no sistema">
                <Link href="/admin/users" className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                    Voltar
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 max-w-2xl">
                <div className="rounded-xl bg-indigo-50 border border-indigo-100 px-4 py-3 mb-6 text-sm text-indigo-800">
                    Um email com link de configuração de senha será enviado para o usuário. O link expira em 7 dias.
                </div>

                <div className="space-y-6">
                    {fields.map(({ field, label, type, placeholder }) => (
                        <div key={field}>
                            <label htmlFor={field} className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>
                            <input
                                id={field}
                                type={type}
                                value={data[field]}
                                onChange={(e) => setData(field, e.target.value)}
                                placeholder={placeholder}
                                className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow"
                            />
                            {errors[field] && <p className="mt-1.5 text-xs text-rose-600">{errors[field]}</p>}
                        </div>
                    ))}

                    <div>
                        <label htmlFor="role" className="block text-sm font-medium text-gray-700 mb-1.5">Papel</label>
                        <div className="grid grid-cols-3 gap-3">
                            {[
                                { value: 'aluno', label: 'Aluno', desc: 'Aluno matriculado' },
                                { value: 'professor', label: 'Professor', desc: 'Ministra aulas' },
                                { value: 'school_admin', label: 'Admin', desc: 'Acesso total' },
                            ].map((role) => (
                                <button
                                    key={role.value}
                                    type="button"
                                    onClick={() => setData('role', role.value)}
                                    className={`p-3 rounded-xl border-2 text-left transition-colors ${
                                        data.role === role.value
                                            ? 'border-indigo-600 bg-indigo-50'
                                            : 'border-gray-200 hover:border-gray-300'
                                    }`}
                                >
                                    <p className={`text-sm font-medium ${data.role === role.value ? 'text-indigo-700' : 'text-gray-900'}`}>{role.label}</p>
                                    <p className="text-xs text-gray-500 mt-0.5">{role.desc}</p>
                                </button>
                            ))}
                        </div>
                        {errors.role && <p className="mt-1.5 text-xs text-rose-600">{errors.role}</p>}
                    </div>
                </div>

                <div className="flex items-center gap-3 mt-8 pt-6 border-t border-gray-100">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Enviando convite...' : 'Enviar convite'}
                    </button>
                    <Link href="/admin/users" className="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                        Cancelar
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
