import { Link, usePage } from '@inertiajs/react';
import Sidebar from '@/Components/Sidebar';
import TopNav from '@/Components/TopNav';

export default function AppLayout({ children, title }) {
    const { flash } = usePage().props;

    return (
        <div className="flex h-screen bg-gray-50 overflow-hidden">
            <Sidebar />
            <div className="flex flex-col flex-1 min-w-0 overflow-hidden">
                <TopNav title={title} />
                <main className="flex-1 overflow-y-auto">
                    <div className="p-6 lg:p-8 max-w-screen-xl mx-auto">
                        {flash?.success && (
                            <div className="mb-5 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-emerald-800 text-sm">
                                <span className="text-emerald-500">✓</span>
                                {flash.success}
                            </div>
                        )}
                        {flash?.error && (
                            <div className="mb-5 flex items-center gap-3 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-rose-800 text-sm">
                                <span className="text-rose-500">✕</span>
                                {flash.error}
                            </div>
                        )}
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
