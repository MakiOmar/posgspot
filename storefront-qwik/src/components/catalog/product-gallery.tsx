import { component$, useSignal, useTask$ } from "@builder.io/qwik";
import { ImageLightbox, type LightboxImage } from "~/components/ui/image-lightbox";
import { tStatic, useI18n } from "~/lib/i18n/context";

interface ProductGalleryProps {
  images: string[];
  alt: string;
}

/** PDP image gallery: main image + thumbnail strip; main opens lightbox. */
export const ProductGallery = component$<ProductGalleryProps>(({ images, alt }) => {
  const { locale } = useI18n();
  const activeIndex = useSignal(0);
  const lightboxIndex = useSignal<number | null>(null);

  // Reset selection when the image set changes (e.g. variation switch).
  useTask$(({ track }) => {
    track(() => images.join("\0"));
    activeIndex.value = 0;
  });

  const active = images[activeIndex.value] || images[0] || null;
  const lightboxImages: LightboxImage[] = images.map((src) => ({ src, alt }));

  return (
    <div class="pdp-gallery">
      <div class="pdp-gallery__main">
        {active ? (
          <button
            type="button"
            class="pdp-gallery__main-btn"
            aria-label={tStatic(locale, "a11y.lightboxOpen")}
            onClick$={() => {
              lightboxIndex.value = activeIndex.value;
            }}
          >
            <img
              src={active}
              alt={alt}
              width={600}
              height={600}
              loading="eager"
              fetchPriority="high"
            />
          </button>
        ) : (
          <div class="pdp-gallery__placeholder" aria-hidden="true" />
        )}
      </div>

      {images.length > 1 ? (
        <ul class="pdp-gallery__thumbs" role="list">
          {images.map((src, index) => (
            <li key={src}>
              <button
                type="button"
                class={`pdp-gallery__thumb${index === activeIndex.value ? " pdp-gallery__thumb--active" : ""}`}
                aria-label={`${alt} ${index + 1}`}
                aria-current={index === activeIndex.value ? "true" : undefined}
                onClick$={() => {
                  activeIndex.value = index;
                }}
              >
                <img src={src} alt="" width={72} height={72} loading="lazy" />
              </button>
            </li>
          ))}
        </ul>
      ) : null}

      {lightboxImages.length > 0 ? (
        <ImageLightbox images={lightboxImages} index={lightboxIndex} />
      ) : null}
    </div>
  );
});

/** Prefer variation images when present, then product images (deduped). */
export function galleryImagesForVariation(
  productImages: string[],
  variationImages: string[],
): string[] {
  const seen = new Set<string>();
  const out: string[] = [];
  for (const src of [...variationImages, ...productImages]) {
    if (!src || seen.has(src)) {
      continue;
    }
    seen.add(src);
    out.push(src);
  }
  return out;
}
