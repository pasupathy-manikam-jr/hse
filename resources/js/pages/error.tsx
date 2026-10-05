import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, home } from '@/routes';

const MESSAGES: Record<number, [string, string]> = {
    403: [
        'You do not have access to this page',
        'Your role does not include this part of the system. Ask an administrator if you need it.',
    ],
    404: [
        'Page not found',
        'This page does not exist, or the record is outside your site.',
    ],
    503: [
        'Down for maintenance',
        'The system is being updated. Try again in a few minutes.',
    ],
};

export default function ErrorPage({
    status,
    signedIn,
}: {
    status: number;
    signedIn: boolean;
}) {
    const [title, text] = MESSAGES[status] ?? [
        'Something went wrong',
        'The error has been logged. Try again, and tell your administrator if it keeps happening.',
    ];

    return (
        <>
            <Head title={title} />
            <main className="flex min-h-screen flex-col items-center justify-center gap-6 bg-background p-6 text-center text-foreground">
                <span className="flex size-12 items-center justify-center rounded-lg bg-green-700">
                    <AppLogoIcon className="size-7 fill-current text-white" />
                </span>
                <div className="grid max-w-md gap-2">
                    <p className="text-sm font-medium text-muted-foreground">
                        {status}
                    </p>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground">{text}</p>
                </div>
                <Button asChild>
                    <Link href={signedIn ? dashboard() : home()}>
                        <ArrowLeft />
                        {signedIn ? 'Back to dashboard' : 'Back to home'}
                    </Link>
                </Button>
            </main>
        </>
    );
}
