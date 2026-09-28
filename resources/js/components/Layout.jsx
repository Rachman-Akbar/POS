import TopHeader from './TopHeader';

export default function Layout({ header = {}, children }) {
    return (
        <div className="min-h-screen bg-page flex flex-col">
            <TopHeader {...header} />
            <main className="flex-1 w-full mx-auto max-w-[1600px] px-4 md:px-6 pt-2 md:pt-3 pb-4 md:pb-6">{children}</main>
        </div>
    );
}