const roleConfig = {
    admin: { label: 'Admin', className: 'bg-rose-50 text-rose-700 border border-rose-200' },
    professor: { label: 'Professor', className: 'bg-sky-50 text-sky-700 border border-sky-200' },
    aluno: { label: 'Aluno', className: 'bg-emerald-50 text-emerald-700 border border-emerald-200' },
};

export default function Badge({ role, label, className = '' }) {
    const config = role ? roleConfig[role] : null;
    const displayLabel = label ?? config?.label ?? role;
    const displayClass = className || config?.className || 'bg-gray-50 text-gray-700 border border-gray-200';

    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${displayClass}`}>
            {displayLabel}
        </span>
    );
}
