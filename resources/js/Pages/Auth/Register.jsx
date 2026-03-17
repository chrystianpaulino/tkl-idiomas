import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e) {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    }

    return (
        <>
            <Head title="Criar conta — TKL Idiomas" />
            <div className="min-h-screen flex">
                {/* Left Panel — Brand */}
                <div className="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 flex-col justify-between p-12">
                    <div>
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center">
                                <span className="text-white font-bold text-lg">T</span>
                            </div>
                            <span className="text-white font-bold text-xl">TKL Idiomas</span>
                        </div>
                    </div>
                    <div className="space-y-4">
                        <h1 className="text-4xl font-bold text-white leading-tight">
                            Gerencie sua escola<br />
                            <span className="text-indigo-400">com eficiência</span>
                        </h1>
                        <p className="text-slate-400 text-lg">
                            Controle de pacotes, aulas, turmas e materiais em um só lugar.
                        </p>
                    </div>
                    <div className="flex items-center gap-6">
                        {['Pacotes de Aulas', 'Turmas', 'Materiais'].map(f => (
                            <div key={f} className="flex items-center gap-2">
                                <div className="w-1.5 h-1.5 bg-indigo-400 rounded-full" />
                                <span className="text-slate-400 text-sm">{f}</span>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Right Panel — Form */}
                <div className="flex-1 flex items-center justify-center p-8 bg-gray-50">
                    <div className="w-full max-w-md">
                        {/* Mobile logo */}
                        <div className="flex items-center gap-3 mb-8 lg:hidden">
                            <div className="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center">
                                <span className="text-white font-bold">T</span>
                            </div>
                            <span className="font-bold text-gray-900 text-lg">TKL Idiomas</span>
                        </div>

                        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
                            <div className="mb-8">
                                <h2 className="text-2xl font-bold text-gray-900">Criar sua conta</h2>
                                <p className="text-gray-500 mt-1 text-sm">Preencha os dados abaixo para começar</p>
                            </div>

                            <form onSubmit={submit} className="space-y-5">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Nome</label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={e => setData('name', e.target.value)}
                                        autoComplete="name"
                                        placeholder="Seu nome completo"
                                        required
                                        className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition placeholder-gray-400"
                                    />
                                    {errors.name && <p className="mt-1.5 text-xs text-rose-600">{errors.name}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={e => setData('email', e.target.value)}
                                        autoComplete="username"
                                        placeholder="seu@email.com"
                                        required
                                        className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition placeholder-gray-400"
                                    />
                                    {errors.email && <p className="mt-1.5 text-xs text-rose-600">{errors.email}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Senha</label>
                                    <input
                                        type="password"
                                        value={data.password}
                                        onChange={e => setData('password', e.target.value)}
                                        autoComplete="new-password"
                                        placeholder="••••••••"
                                        required
                                        className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition placeholder-gray-400"
                                    />
                                    {errors.password && <p className="mt-1.5 text-xs text-rose-600">{errors.password}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Confirmar senha</label>
                                    <input
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={e => setData('password_confirmation', e.target.value)}
                                        autoComplete="new-password"
                                        placeholder="••••••••"
                                        required
                                        className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition placeholder-gray-400"
                                    />
                                    {errors.password_confirmation && <p className="mt-1.5 text-xs text-rose-600">{errors.password_confirmation}</p>}
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white py-2.5 rounded-xl text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing ? 'Criando conta...' : 'Criar conta'}
                                </button>

                                <p className="text-center text-sm text-gray-500">
                                    Já tem uma conta?{' '}
                                    <Link href={route('login')} className="text-indigo-600 hover:text-indigo-700 font-medium">
                                        Entrar
                                    </Link>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
