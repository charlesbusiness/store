import { Outlet } from 'react-router-dom';
import { Header } from './Header';
import { Footer } from './Footer';
import { FlashMessage } from './FlashMessage';

export function MainLayout() {
    return (
        <div className="w">
            <Header />
            <FlashMessage />
            <main style={{ maxWidth: '80rem', margin: '0 auto' }}>
                <Outlet />
            </main>
            <Footer />
        </div>
    );
}