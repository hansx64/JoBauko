import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';

/**
 * Layout for public pages: a slim header with sign-in links.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <div className="bg-background flex min-h-screen flex-col">
            <header className="border-b">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                    <Link href={route('home')} className="flex items-center gap-2 font-semibold">
                        <span className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-md">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        JoBauko
                    </Link>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild size="sm">
                                <Link href={route('dashboard')}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={route('login')}>Log in</Link>
                                </Button>
                                <Button asChild size="sm">
                                    <Link href={route('register')}>Create an account</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </div>
            </header>
            <main className="flex-1">{children}</main>
            <footer className="text-muted-foreground border-t py-6 text-center text-sm">
                JoBauko · Opportunities for the people of Bauko, Mountain Province ·{' '}
                <Link href={route('privacy')} className="underline underline-offset-4">
                    Privacy Notice
                </Link>
            </footer>
        </div>
    );
}
