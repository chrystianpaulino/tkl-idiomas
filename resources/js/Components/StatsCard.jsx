export default function StatsCard({ title, value, icon, iconBg = 'bg-indigo-50', iconColor = 'text-indigo-600', change }) {
    return (
        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 hover:shadow-md transition-shadow">
            <div className="flex items-start justify-between">
                <div className={`${iconBg} ${iconColor} w-10 h-10 rounded-xl flex items-center justify-center text-xl flex-shrink-0`}>
                    {icon}
                </div>
            </div>
            <div className="mt-4">
                <p className="text-3xl font-bold text-gray-900 tabular-nums">{value ?? 0}</p>
                <p className="text-sm text-gray-500 mt-1">{title}</p>
                {change && <p className="text-xs text-emerald-600 mt-1 font-medium">{change}</p>}
            </div>
        </div>
    );
}
