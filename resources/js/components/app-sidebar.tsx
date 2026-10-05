import { Link } from '@inertiajs/react';
import {
    BookOpen,
    ClipboardCheck,
    Eye,
    FileSearch,
    FileText,
    FlaskConical,
    GraduationCap,
    KeyRound,
    Leaf,
    HardHat,
    LayoutGrid,
    ListChecks,
    ListTodo,
    MapPin,
    Megaphone,
    MessagesSquare,
    Repeat,
    ShieldAlert,
    Siren,
    Users,
} from 'lucide-react';
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
import { dashboard, guide } from '@/routes';
import actions from '@/routes/actions';
import audits from '@/routes/audits';
import checklists from '@/routes/checklists';
import chemicals from '@/routes/chemicals';
import competencies from '@/routes/competencies';
import contractors from '@/routes/contractors';
import documents from '@/routes/documents';
import environment from '@/routes/environment';
import handovers from '@/routes/handovers';
import incidents from '@/routes/incidents';
import inspections from '@/routes/inspections';
import observations from '@/routes/observations';
import permits from '@/routes/permits';
import riskAssessments from '@/routes/risk-assessments';
import toolboxTalks from '@/routes/toolbox-talks';
import sites from '@/routes/sites';
import users from '@/routes/users';
import type { NavItem } from '@/types';

// Grouped by what people come to do; each item shows only with its permission.
const navGroups: { label: string; items: NavItem[] }[] = [
    {
        label: 'Platform',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
        ],
    },
    {
        label: 'Report',
        items: [
            {
                title: 'Report',
                href: observations.create(),
                icon: Megaphone,
                permission: 'create-observations',
            },
            {
                title: 'Observations',
                href: observations.index(),
                icon: Eye,
                permission: 'manage-observations',
            },
            {
                title: 'Incidents',
                href: incidents.index(),
                icon: Siren,
                permission: 'manage-incidents',
            },
            {
                title: 'Actions',
                href: actions.index(),
                icon: ListChecks,
            },
        ],
    },
    {
        label: 'Control',
        items: [
            {
                title: 'Risk assessments',
                href: riskAssessments.index(),
                icon: ShieldAlert,
                permission: 'manage-risk-assessments',
            },
            {
                title: 'Inspections',
                href: inspections.index(),
                icon: ClipboardCheck,
                permission: 'manage-inspections',
            },
            {
                title: 'Audits',
                href: audits.index(),
                icon: FileSearch,
                permission: 'manage-audits',
            },
            {
                title: 'Documents',
                href: documents.index(),
                icon: FileText,
                permission: 'manage-documents',
            },
            {
                title: 'Permits to work',
                href: permits.index(),
                icon: KeyRound,
                permission: 'manage-permits',
            },
            {
                title: 'Shift handovers',
                href: handovers.index(),
                icon: Repeat,
                permission: 'manage-permits',
            },
        ],
    },
    {
        label: 'Environment',
        items: [
            {
                title: 'Environmental log',
                href: environment.index(),
                icon: Leaf,
                permission: 'manage-environment',
            },
            {
                title: 'Chemical register',
                href: chemicals.index(),
                icon: FlaskConical,
                permission: 'manage-chemicals',
            },
        ],
    },
    {
        label: 'People',
        items: [
            {
                title: 'Training matrix',
                href: competencies.index(),
                icon: GraduationCap,
                permission: 'manage-competencies',
            },
            {
                title: 'Toolbox talks',
                href: toolboxTalks.index(),
                icon: MessagesSquare,
                permission: 'manage-toolbox-talks',
            },
        ],
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
        title: 'Checklists',
        href: checklists.index(),
        icon: ListTodo,
        permission: 'manage-checklists',
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
    const allowed = (item: NavItem) => !item.permission || can(item.permission);
    const groups = [...navGroups, { label: 'Setup', items: setupNavItems }]
        .map((g) => ({ ...g, items: g.items.filter(allowed) }))
        .filter((g) => g.items.length > 0);

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
                {groups.map((g) => (
                    <NavMain key={g.label} label={g.label} items={g.items} />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: 'User guide' }}
                        >
                            <Link href={guide()}>
                                <BookOpen />
                                <span>User guide</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
