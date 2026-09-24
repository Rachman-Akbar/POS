import TopHeader from './TopHeader';

export default function Layout({ header = {}, children }) {
    return (
        <div className="min-h-screen bg-gray-50 flex flex-col">
            <TopHeader {...header} />
            <main className="flex-1 w-full mx-auto max-w-[1600px] p-4 md:p-6">{children}</main>
        </div>
    );
}