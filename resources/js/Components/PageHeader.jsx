export default function PageHeader({ title, subtitle, children }) {
    return (
        <div className="flex items-start justify-between mb-6 pb-5 border-b border-gray-200">
            <div>
                <h2 className="text-xl font-bold text-gray-900">{title}</h2>
                {subtitle && <p className="text-sm text-gray-500 mt-0.5">{subtitle}</p>}
            </div>
            {children && <div className="flex items-center gap-2 flex-shrink-0 ml-4">{children}</div>}
        </div>
    );
}
