export default function Avatar({ name, size = 'md', className = '' }) {
    const initials = name
        ? name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
        : '?';
    const colors = ['bg-indigo-500', 'bg-violet-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500', 'bg-sky-500'];
    const colorIndex = name ? name.charCodeAt(0) % colors.length : 0;
    const sizes = { sm: 'w-7 h-7 text-xs', md: 'w-9 h-9 text-sm', lg: 'w-12 h-12 text-base', xl: 'w-16 h-16 text-xl' };
    return (
        <div className={`${colors[colorIndex]} ${sizes[size]} rounded-full flex items-center justify-center text-white font-semibold flex-shrink-0 ${className}`}>
            {initials}
        </div>
    );
}
