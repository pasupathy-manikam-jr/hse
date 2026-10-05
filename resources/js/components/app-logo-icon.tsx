import { useId } from 'react';
import type { SVGAttributes } from 'react';

/**
 * The HSE mark: a shield with the check cut out, so it takes the current fill colour
 * on any background. public/favicon.svg is the same shape in brand green.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    const mask = useId();

    return (
        <svg {...props} viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <mask id={mask}>
                <rect width="32" height="32" fill="white" />
                <path
                    d="m10.5 16 3.8 3.8 7.2-7.5"
                    fill="none"
                    stroke="black"
                    strokeWidth="2.8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </mask>
            <path
                mask={`url(#${mask})`}
                d="M16 2 28 6.5V15c0 7.5-5.2 12.8-12 15C9.2 27.8 4 22.5 4 15V6.5Z"
            />
        </svg>
    );
}
