import { component$, useSignal, useTask$ } from "@builder.io/qwik";

interface ProductGalleryProps {
  images: string[];
  alt: string;
}

/** PDP image gallery: main image + thumbnail strip when multiple images exist. */
export const ProductGallery = component$<ProductGalleryProps>(({ images, alt }) => {
  const activeIndex = useSignal(0);

  // Reset selection when the image set changes (e.g. variation switch).
  useTask$(({ track }) => {
    track(() => images.join("\0"));
    activeIndex.value = 0;
  });

  const active = images[activeIndex.value] || images[0] || null;

  return (
    <div class="pdp-gallery">
      <div class="pdp-gallery__main">
        {active ? (
          <img
            src={active}
            alt={alt}
            width={600}
            height={600}
            loading="eager"
            fetchPriority="high"
          />
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
