import { Head } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard, guide } from '@/routes';

/**
 * The user guide. The HTML is rendered on the server from resources/guide/en.md with any HTML
 * in the file escaped, so it is safe to insert.
 */
export default function Guide({ html }: { html: string }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('User guide')} />
            <div className="p-4 md:p-6">
                <article
                    className="guide mx-auto max-w-3xl"
                    dangerouslySetInnerHTML={{ __html: html }}
                />
            </div>
        </>
    );
}

Guide.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'User guide', href: guide() },
    ],
};
