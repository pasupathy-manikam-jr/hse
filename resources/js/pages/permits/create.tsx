import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, Send } from 'lucide-react';
import { localDateTime } from '@/components/date-picker';
import { PageHeader } from '@/components/page-header';
import { PermitFields, permitPayload } from '@/components/permit-fields';
import type { PermitFormData, PermitOptions } from '@/components/permit-fields';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import permitRoutes from '@/routes/permits';

// Default window: from now for 8 hours.
const inHours = (hours: number) =>
    localDateTime(new Date(Date.now() + hours * 3600_000).toISOString());

export default function RequestPermit(options: PermitOptions) {
    const { t } = useTranslation();
    const form = useForm<PermitFormData>({
        type: '',
        site_id: String(options.sites.length === 1 ? options.sites[0].id : ''),
        area_id: '',
        risk_assessment_id: '',
        contractor_id: '',
        description: '',
        valid_from: inHours(0),
        valid_to: inHours(8),
        worker_ids: [],
    });

    return (
        <>
            <Head title={t('Request permit')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Request permit"
                    description="What, where, when and who. Precautions, gas tests and isolations are recorded on the next page."
                    action={
                        <Button variant="outline" asChild>
                            <Link href={permitRoutes.index()}>
                                <ArrowLeft /> {t('Back')}
                            </Link>
                        </Button>
                    }
                />
                <form
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform(permitPayload);
                        form.post(permitRoutes.store.url());
                    }}
                    className="grid max-w-3xl gap-6 rounded-xl border p-5"
                >
                    <PermitFields form={form} options={options} />
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? (
                                <LoaderCircle className="animate-spin" />
                            ) : (
                                <Send />
                            )}
                            {t('Request permit')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

RequestPermit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Permits to work', href: permitRoutes.index() },
        { title: 'Request', href: permitRoutes.create() },
    ],
};
