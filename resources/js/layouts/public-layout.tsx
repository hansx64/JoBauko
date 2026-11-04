import { LogoPair } from '@/components/app-logo-icon';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { type ReactNode } from 'react';

const links = [
    { label: 'Opportunities', href: '/opportunities' },
    { label: 'For organizations', href: '/organizations/register' },
];

/**
 * Layout for public pages: navigation, content and footer.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage<SharedData>().props;
    const { url } = usePage();

    const accountLinks = auth.user ? (
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
    );

    return (
        <div className="bg-background flex min-h-screen flex-col">
            <header className="bg-background/90 sticky top-0 z-40 border-b backdrop-blur">
                <div className="bauko-weave h-1" aria-hidden />
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-2.5">
                    <Link href={route('home')} className="flex items-center gap-2.5">
                        <LogoPair className="size-10" />
                        <span className="grid leading-tight">
                            <span className="font-semibold">JoBauko</span>
                            <span className="text-muted-foreground text-xs">Municipality of Bauko</span>
                        </span>
                    </Link>

                    <nav className="hidden items-center gap-1 md:flex">
                        {links.map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                className={cn(
                                    'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                    url.startsWith(link.href) ? 'text-primary' : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {link.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="hidden items-center gap-2 md:flex">
                        <AppearanceToggleDropdown />
                        {accountLinks}
                    </div>

                    <div className="flex items-center gap-1 md:hidden">
                        <AppearanceToggleDropdown />
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button variant="ghost" size="icon" aria-label="Open menu">
                                    <Menu className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="right" className="w-72">
                                <SheetHeader>
                                    <SheetTitle>JoBauko</SheetTitle>
                                </SheetHeader>
                                <nav className="grid gap-1 px-4">
                                    {links.map((link) => (
                                        <Link key={link.href} href={link.href} className="hover:bg-muted rounded-md px-3 py-2 font-medium">
                                            {link.label}
                                        </Link>
                                    ))}
                                </nav>
                                <div className="flex flex-col gap-2 px-4">{accountLinks}</div>
                            </SheetContent>
                        </Sheet>
                    </div>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="bg-muted/40 border-t">
                <div className="bauko-weave h-1" aria-hidden />
                <div className="text-muted-foreground mx-auto grid max-w-6xl gap-6 px-4 py-10 text-sm sm:grid-cols-3">
                    <div className="space-y-2">
                        <p className="text-foreground flex items-center gap-2 font-semibold">
                            <LogoPair className="size-8" /> JoBauko
                        </p>
                        <p>Jobs, scholarships, livelihood programs and trainings for the people of Bauko, Mountain Province.</p>
                    </div>
                    <div className="space-y-2">
                        <p className="text-foreground font-medium">Explore</p>
                        <ul className="space-y-1">
                            <li>
                                <Link href="/opportunities?category=job" className="hover:text-foreground">
                                    Jobs
                                </Link>
                            </li>
                            <li>
                                <Link href="/opportunities?category=scholarship" className="hover:text-foreground">
                                    Scholarships
                                </Link>
                            </li>
                            <li>
                                <Link href="/opportunities?category=livelihood" className="hover:text-foreground">
                                    Livelihood programs
                                </Link>
                            </li>
                            <li>
                                <Link href="/opportunities?category=training" className="hover:text-foreground">
                                    Trainings and seminars
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div className="space-y-2">
                        <p className="text-foreground font-medium">JoBauko</p>
                        <ul className="space-y-1">
                            <li>
                                <Link href={route('organizations.register')} className="hover:text-foreground">
                                    Post an opportunity
                                </Link>
                            </li>
                            <li>
                                <Link href={route('privacy')} className="hover:text-foreground">
                                    Privacy Notice
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </footer>
        </div>
    );
}
