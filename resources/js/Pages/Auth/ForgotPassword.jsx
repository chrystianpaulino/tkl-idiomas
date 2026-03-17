import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    function submit(e) {
        e.preventDefault();
        post(route('password.email'));
    }

    return (
        <>
            <Head title="Esqueci minha senha — TKL Idiomas" />
            <div className="min-h-screen flex items-center justify-center bg-gray-50 p-8">
                <div className="w-full max-w-md">
                    {/* Logo */}
                    <div className="flex items-center justify-center gap-3 mb-8">
                        <div className="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center">
                            <span className="text-white font-bold text-lg">T</span>
                        </div>
                        <span className="font-bold text-gray-900 text-xl">TKL Idiomas</span>
                    </div>

                    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
                        <div className="mb-8">
                            <h2 className="text-2xl font-bold text-gray-900">Esqueceu sua senha?</h2>
                            <p className="text-gray-500 mt-1 text-sm">
                                Sem problemas. Informe seu email e enviaremos um link para redefinir sua senha.
                            </p>
                        </div>

                        {status && (
                            <div className="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-emerald-700 text-sm">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={e => setData('email', e.target.value)}
                                    autoComplete="username"
                                    placeholder="seu@email.com"
                                    className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition placeholder-gray-400"
                                />
                                {errors.email && <p className="mt-1.5 text-xs text-rose-600">{errors.email}</p>}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white py-2.5 rounded-xl text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? 'Enviando...' : 'Enviar link de redefinição'}
                            </button>

                            <p className="text-center text-sm text-gray-500">
                                <Link href={route('login')} className="text-indigo-600 hover:text-indigo-700 font-medium">
                                    Voltar para o login
                                </Link>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
