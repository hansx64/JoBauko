import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type AccountType, type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, ShieldCheck, UserRound } from 'lucide-react';
import AppLogo from './app-logo';

const navItemsByAccountType: Record<AccountType, NavItem[]> = {
    seeker: [
        {
            title: 'Dashboard',
            url: '/dashboard',
            icon: LayoutGrid,
        },
        {
            title: 'My profile',
            url: '/profile',
            icon: UserRound,
        },
    ],
    organization: [],
    admin: [
        {
            title: 'Admin dashboard',
            url: '/admin/dashboard',
            icon: ShieldCheck,
        },
    ],
};

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const navItems = navItemsByAccountType[auth.user.account_type];
    const homeUrl = navItems[0]?.url ?? '/dashboard';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
