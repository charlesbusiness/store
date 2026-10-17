import { Outlet } from 'react-router-dom';
import { Header } from './Header';
import { Footer } from './Footer';
import { FlashMessage } from './FlashMessage';

export function MainLayout() {
    return (
        <div className="w">
            <Header />
            <FlashMessage />
            <main>
                <Outlet />
            </main>
            <Footer />
        </div>
    );
}