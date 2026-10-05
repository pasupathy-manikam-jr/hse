import { Camera, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';

/** Matches HasPhotos::PHOTO_MAX on the server. */
export const MAX_PHOTOS = 5;

/** Longest side after shrinking: plenty to see a hazard, a fraction of a phone photo's size. */
const MAX_SIDE = 1600;

/**
 * Shrink a photo before upload (JPEG, longest side MAX_SIDE) to spare mobile data and storage.
 * Small images, and anything the browser cannot decode, are sent as they are.
 */
async function shrink(file: File): Promise<File> {
    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(
            1,
            MAX_SIDE / Math.max(bitmap.width, bitmap.height),
        );

        if (scale === 1 && file.size < 1_000_000) {
            bitmap.close();

            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas
            .getContext('2d')
            ?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', 0.8),
        );

        return blob
            ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', {
                  type: 'image/jpeg',
              })
            : file;
    } catch {
        return file;
    }
}

/**
 * Up to five photos from the camera or gallery, with thumbnails and remove buttons.
 * Holds File objects; the form posts them as photos[].
 */
export function PhotoPicker({
    photos,
    onChange,
    errors,
}: {
    photos: File[];
    onChange: (photos: File[]) => void;
    errors: Record<string, string>;
}) {
    const { t } = useTranslation();
    const [previews, setPreviews] = useState<string[]>([]);
    const error = Object.entries(errors).find(([key]) =>
        key.startsWith('photos'),
    )?.[1];

    // Thumbnails for the chosen photos; revoked when they change.
    useEffect(() => {
        const urls = photos.map((file) => URL.createObjectURL(file));
        setPreviews(urls);

        return () => urls.forEach((url) => URL.revokeObjectURL(url));
    }, [photos]);

    return (
        <div className="grid gap-2">
            <span className="text-sm font-medium">
                {t('Photos')}{' '}
                <span className="font-normal text-muted-foreground">
                    ({photos.length}/{MAX_PHOTOS})
                </span>
            </span>
            <div className="grid grid-cols-3 gap-2 sm:grid-cols-5">
                {previews.map((url, i) => (
                    <div key={url} className="relative">
                        <img
                            src={url}
                            alt={t('Photo :n', { n: i + 1 })}
                            className="aspect-square w-full rounded-md object-cover"
                        />
                        <button
                            type="button"
                            aria-label={t('Remove photo :n', { n: i + 1 })}
                            onClick={() =>
                                onChange(photos.filter((_, j) => j !== i))
                            }
                            className="absolute end-1 top-1 rounded-full bg-black/60 p-1 text-white"
                        >
                            <X className="size-3" />
                        </button>
                    </div>
                ))}
                {photos.length < MAX_PHOTOS && (
                    <label className="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed text-sm text-muted-foreground has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50">
                        <Camera className="size-6" />
                        {t('Add')}
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            className="sr-only"
                            onChange={async (e) => {
                                const picked = Array.from(e.target.files ?? []);
                                e.target.value = '';
                                const shrunk = await Promise.all(
                                    picked
                                        .slice(0, MAX_PHOTOS - photos.length)
                                        .map(shrink),
                                );
                                onChange([...photos, ...shrunk]);
                            }}
                        />
                    </label>
                )}
            </div>
            <InputError message={error} />
        </div>
    );
}

/** Saved photos of a record, opening full size in a new tab. */
export function PhotoGallery({
    photos,
    url,
}: {
    photos: { id: number }[];
    url: (id: number) => string;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {photos.map((photo, i) => (
                <a
                    key={photo.id}
                    href={url(photo.id)}
                    target="_blank"
                    rel="noreferrer"
                >
                    <img
                        src={url(photo.id)}
                        alt={t('Photo :n', { n: i + 1 })}
                        loading="lazy"
                        className="aspect-square w-full rounded-md border object-cover"
                    />
                </a>
            ))}
        </div>
    );
}
