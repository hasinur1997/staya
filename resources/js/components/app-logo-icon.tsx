import type { SVGAttributes } from 'react';

/**
 * The Staya mark: a house with a steaming bowl. It carries its own brand
 * colours (the `logo-*` tokens), so it looks the same on any background.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="-2 -2 132 126"
            xmlns="http://www.w3.org/2000/svg"
        >
            <polygon
                points="64,6.59 123,57.3 123,117 5,117 5,57.3"
                strokeWidth="10"
                strokeLinejoin="round"
                className="fill-logo-house stroke-logo-house"
            />
            <path d="M31 74H97A33 35 0 0 1 31 74Z" className="fill-logo-ink" />
            <g
                fill="none"
                strokeWidth="5"
                strokeLinecap="round"
                className="stroke-logo-ink"
            >
                <path d="M54 45 q-5 5.5 0 11 t0 11" />
                <path d="M74 45 q-5 5.5 0 11 t0 11" />
            </g>
        </svg>
    );
}
