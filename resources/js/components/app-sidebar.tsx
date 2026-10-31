import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type AccountType, type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Bookmark,
    Building2,
    ClipboardList,
    Flag,
    LayoutGrid,
    Megaphone,
    MessageSquareText,
    ScrollText,
    Search,
    Send,
    ShieldCheck,
    UserRound,
    Users,
} from 'lucide-react';
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
            title: 'Applicants',
            url: '/org/applications',
            icon: ClipboardList,
            permission: 'view_applications',
        },
        {
            title: 'Personnel',
            url: '/org/personnel',
            icon: Users,
            permission: 'manage_personnel',
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
        {
            title: 'Users',
            url: '/admin/users',
            icon: Users,
        },
        {
            title: 'Opportunities',
            url: '/admin/opportunities',
            icon: Megaphone,
        },
        {
            title: 'Applications',
            url: '/admin/applications',
            icon: ClipboardList,
        },
        {
            title: 'Reports',
            url: '/admin/reports',
            icon: Flag,
        },
        {
            title: 'Announcements',
            url: '/admin/announcements',
            icon: Send,
        },
        {
            title: 'SMS templates',
            url: '/admin/sms-templates',
            icon: MessageSquareText,
        },
        {
            title: 'Delivery logs',
            url: '/admin/delivery-logs',
            icon: ScrollText,
        },
    ],
};

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const permissions = auth.organization?.permissions ?? [];
    const navItems = navItemsByAccountType[auth.user.account_type].filter((item) => !item.permission || permissions.includes(item.permission));
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
