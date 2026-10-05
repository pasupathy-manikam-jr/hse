import { Link } from '@inertiajs/react';
import { HardHat, LayoutGrid, MapPin, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCan } from '@/hooks/use-can';
import { dashboard } from '@/routes';
import contractors from '@/routes/contractors';
import sites from '@/routes/sites';
import users from '@/routes/users';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const setupNavItems: NavItem[] = [
    {
        title: 'Sites',
        href: sites.index(),
        icon: MapPin,
        permission: 'manage-sites',
    },
    {
        title: 'Contractors',
        href: contractors.index(),
        icon: HardHat,
        permission: 'manage-contractors',
    },
    {
        title: 'Users',
        href: users.index(),
        icon: Users,
        permission: 'manage-users',
    },
];

export function AppSidebar() {
    const can = useCan();
    const setup = setupNavItems.filter(
        (item) => !item.permission || can(item.permission),
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {setup.length > 0 && <NavMain label="Setup" items={setup} />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
