import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type AccountType, type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bookmark, Building2, ClipboardList, LayoutGrid, Megaphone, Search, ShieldCheck, UserRound } from 'lucide-react';
import AppLogo from './app-logo';

const navItemsByAccountType: Record<AccountType, NavItem[]> = {
    seeker: [
        {
            title: 'Dashboard',
            url: '/dashboard',
            icon: LayoutGrid,
        },
        {
            title: 'Browse opportunities',
            url: '/opportunities',
            icon: Search,
        },
        {
            title: 'My applications',
            url: '/applications',
            icon: ClipboardList,
        },
        {
            title: 'Saved',
            url: '/saved',
            icon: Bookmark,
        },
        {
            title: 'My profile',
            url: '/profile',
            icon: UserRound,
        },
    ],
    organization: [
        {
            title: 'Dashboard',
            url: '/org/dashboard',
            icon: LayoutGrid,
        },
        {
            title: 'Opportunities',
            url: '/org/opportunities',
            icon: Megaphone,
        },
        {
            title: 'Organization profile',
            url: '/org/profile',
            icon: Building2,
        },
    ],
    admin: [
        {
            title: 'Admin dashboard',
            url: '/admin/dashboard',
            icon: ShieldCheck,
        },
        {
            title: 'Organizations',
            url: '/admin/organizations',
            icon: Building2,
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
