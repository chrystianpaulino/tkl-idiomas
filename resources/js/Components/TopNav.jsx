import { usePage } from '@inertiajs/react';
import Avatar from '@/Components/Avatar';

export default function TopNav({ title }) {
    const { auth } = usePage().props;

    return (
        <header className="bg-white border-b border-gray-200 px-6 lg:px-8 py-4 flex items-center justify-between flex-shrink-0">
            <h1 className="text-base font-semibold text-gray-900 truncate">{title}</h1>
            <div className="flex items-center gap-3 flex-shrink-0">
                <Avatar name={auth?.user?.name} size="sm" />
                <div className="hidden sm:block text-right">
                    <p className="text-xs font-medium text-gray-900 leading-tight">{auth?.user?.name}</p>
                    <p className="text-xs text-gray-400">{auth?.user?.email}</p>
                </div>
            </div>
        </header>
    );
}
