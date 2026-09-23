export default function Tabs({ tabs = [], active, onChange }) {
    return (
        <div className="flex items-center gap-2 mb-6">
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    onClick={() => onChange(tab.key)}
                    className={`btn border text-sm ${
                        active === tab.key ? 'bg-orange-600 text-white border-orange-600' : 'border-gray-300 text-gray-600'
                    }`}
                >
                    {tab.icon && <tab.icon size={15} />}
                    {tab.label}
                </button>
            ))}
        </div>
    );
}