/**
 * E-commerce SVG icon set.
 *
 * All icons are inline Qwik components so they ship in the consuming route's
 * chunk (no extra HTTP request) and inherit color via `currentColor`. Size them
 * with the `size` prop (px) and color them with CSS `color` on a parent.
 *
 * Outline icons follow a 24x24 viewBox, 1.75 stroke. Brand icons are filled.
 */
import { component$, Slot, type QwikIntrinsicElements } from "@builder.io/qwik";

export interface IconProps {
  /** Square size in pixels (width and height). Default 20. */
  size?: number;
  /** Extra class names for styling. */
  class?: string;
  /** Accessible label; when omitted the icon is marked decorative. */
  title?: string;
}

type SvgProps = QwikIntrinsicElements["svg"];

/** Shared outline SVG wrapper (stroke = currentColor). */
const OutlineSvg = component$<IconProps & SvgProps>(
  ({ size = 20, title, ...rest }) => {
    return (
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width={size}
        height={size}
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden={title ? undefined : "true"}
        role={title ? "img" : undefined}
        {...rest}
      >
        {title ? <title>{title}</title> : null}
        <Slot />
      </svg>
    );
  },
);

/** Shared filled SVG wrapper for brand glyphs (fill = currentColor). */
const BrandSvg = component$<IconProps & SvgProps>(
  ({ size = 20, title, ...rest }) => {
    return (
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width={size}
        height={size}
        viewBox="0 0 24 24"
        fill="currentColor"
        aria-hidden={title ? undefined : "true"}
        role={title ? "img" : undefined}
        {...rest}
      >
        {title ? <title>{title}</title> : null}
        <Slot />
      </svg>
    );
  },
);

export const SearchIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <circle cx="11" cy="11" r="8" />
    <path d="m21 21-4.3-4.3" />
  </OutlineSvg>
));

export const CartIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <circle cx="8" cy="21" r="1" />
    <circle cx="19" cy="21" r="1" />
    <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 2-1.58l1.65-7.42H5.12" />
  </OutlineSvg>
));

export const UserIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
    <circle cx="12" cy="7" r="4" />
  </OutlineSvg>
));

export const HeartIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.49 4.04 3 5.5l7 7Z" />
  </OutlineSvg>
));

export const MenuIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <line x1="3" y1="6" x2="21" y2="6" />
    <line x1="3" y1="12" x2="21" y2="12" />
    <line x1="3" y1="18" x2="21" y2="18" />
  </OutlineSvg>
));

export const CloseIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <line x1="18" y1="6" x2="6" y2="18" />
    <line x1="6" y1="6" x2="18" y2="18" />
  </OutlineSvg>
));

export const CheckIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <polyline points="20 6 9 17 4 12" />
  </OutlineSvg>
));

export const CheckCircleIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
    <polyline points="22 4 12 14.01 9 11.01" />
  </OutlineSvg>
));

export const CrossIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <circle cx="12" cy="12" r="10" />
    <line x1="15" y1="9" x2="9" y2="15" />
    <line x1="9" y1="9" x2="15" y2="15" />
  </OutlineSvg>
));

export const MapPinIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z" />
    <circle cx="12" cy="10" r="3" />
  </OutlineSvg>
));

export const PhoneIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z" />
  </OutlineSvg>
));

export const MailIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <rect x="2" y="4" width="20" height="16" rx="2" />
    <path d="m22 7-10 6L2 7" />
  </OutlineSvg>
));

export const TruckIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <path d="M14 18V6a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h2" />
    <path d="M14 9h4l3 3v5a1 1 0 0 1-1 1h-2" />
    <circle cx="7" cy="18" r="2" />
    <circle cx="17" cy="18" r="2" />
  </OutlineSvg>
));

export const ShieldIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
    <polyline points="9 12 11 14 15 10" />
  </OutlineSvg>
));

export const ChevronLeftIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <polyline points="15 18 9 12 15 6" />
  </OutlineSvg>
));

export const ChevronRightIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <polyline points="9 18 15 12 9 6" />
  </OutlineSvg>
));

export const TrashIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <polyline points="3 6 5 6 21 6" />
    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
    <line x1="10" y1="11" x2="10" y2="17" />
    <line x1="14" y1="11" x2="14" y2="17" />
  </OutlineSvg>
));

export const PlusIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <line x1="12" y1="5" x2="12" y2="19" />
    <line x1="5" y1="12" x2="19" y2="12" />
  </OutlineSvg>
));

export const MinusIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <line x1="5" y1="12" x2="19" y2="12" />
  </OutlineSvg>
));

export const StarIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
  </OutlineSvg>
));

export const FilterIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
  </OutlineSvg>
));

export const FacebookIcon = component$<IconProps>((props) => (
  <BrandSvg {...props}>
    <path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z" />
  </BrandSvg>
));

export const InstagramIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <rect x="2" y="2" width="20" height="20" rx="5" />
    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37Z" />
    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
  </OutlineSvg>
));

export const TiktokIcon = component$<IconProps>((props) => (
  <BrandSvg {...props}>
    <path d="M16.6 5.82A4.28 4.28 0 0 1 15.5 3h-3.1v12.4a2.6 2.6 0 1 1-2.6-2.6c.27 0 .52.04.77.11V9.74a5.7 5.7 0 0 0-.77-.05 5.7 5.7 0 1 0 5.7 5.7V9.01a7.34 7.34 0 0 0 4.2 1.32V7.23a4.28 4.28 0 0 1-3.1-1.41Z" />
  </BrandSvg>
));

export const YoutubeIcon = component$<IconProps>((props) => (
  <BrandSvg {...props}>
    <path d="M23 12s0-3.2-.41-4.74a2.5 2.5 0 0 0-1.76-1.77C19.29 5.07 12 5.07 12 5.07s-7.29 0-8.83.42A2.5 2.5 0 0 0 1.41 7.26 26.07 26.07 0 0 0 1 12a26.07 26.07 0 0 0 .41 4.74 2.5 2.5 0 0 0 1.76 1.77c1.54.42 8.83.42 8.83.42s7.29 0 8.83-.42a2.5 2.5 0 0 0 1.76-1.77C23 15.2 23 12 23 12ZM9.75 15.02V8.98L15.5 12Z" />
  </BrandSvg>
));

export const WhatsappIcon = component$<IconProps>((props) => (
  <BrandSvg {...props}>
    <path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51l-.57-.01c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.06 2.87 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.62.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.42-.07-.13-.27-.2-.57-.35ZM12.04 21.5h-.01a9.43 9.43 0 0 1-4.8-1.32l-.34-.2-3.57.94.95-3.48-.22-.36a9.42 9.42 0 0 1-1.44-5.02c0-5.2 4.24-9.43 9.45-9.43a9.4 9.4 0 0 1 9.44 9.44c0 5.2-4.24 9.43-9.45 9.43Zm5.5-14.93A7.72 7.72 0 0 0 12.04 4.3c-4.27 0-7.74 3.47-7.74 7.74 0 1.49.42 2.94 1.21 4.2l.19.3-.8 2.92 2.99-.78.29.17a7.7 7.7 0 0 0 3.93 1.08h.01c4.27 0 7.74-3.47 7.74-7.74a7.7 7.7 0 0 0-2.27-5.48Z" />
  </BrandSvg>
));

export const ShareIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <circle cx="18" cy="5" r="3" />
    <circle cx="6" cy="12" r="3" />
    <circle cx="18" cy="19" r="3" />
    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49" />
    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49" />
  </OutlineSvg>
));

export const LinkIcon = component$<IconProps>((props) => (
  <OutlineSvg {...props}>
    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
  </OutlineSvg>
));

export const XIcon = component$<IconProps>((props) => (
  <BrandSvg {...props}>
    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.727-8.835L1.254 2.25H8.08l4.253 5.622L18.244 2.25Zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77Z" />
  </BrandSvg>
));

