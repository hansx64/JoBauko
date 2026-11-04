import { ImgHTMLAttributes } from 'react';

import { cn } from '@/lib/utils';

type AppLogoIconProps = ImgHTMLAttributes<HTMLImageElement> & {
    /** Use the 256px image for large displays (above about 64px). */
    large?: boolean;
};

/**
 * The official seal of the Municipality of Bauko. Pass alt="" when the name
 * "JoBauko" is written right next to it.
 */
export default function AppLogoIcon({ large = false, className, alt = 'Seal of the Municipality of Bauko', ...props }: AppLogoIconProps) {
    const size = large ? 256 : 128;

    return (
        <img
            src={large ? '/images/bauko-seal.png' : '/images/bauko-seal-128.png'}
            width={size}
            height={size}
            alt={alt}
            className={cn('shrink-0 rounded-full', className)}
            {...props}
        />
    );
}

/** The logo of Montanosa Tech, who built JoBauko. */
export function MontanosaLogoIcon({ className, alt = 'Montanosa Tech logo', ...props }: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/images/montanosa-tech.png"
            width={256}
            height={256}
            alt={alt}
            className={cn('shrink-0 rounded-full bg-white', className)}
            {...props}
        />
    );
}

/**
 * The municipal seal with the Montanosa Tech logo beside it. `className`
 * sizes each logo; `secondClassName` is added to the second one only (for
 * example to hide it where there is room for one logo).
 */
export function LogoPair({ large = false, className, secondClassName }: { large?: boolean; className?: string; secondClassName?: string }) {
    return (
        <span className="flex shrink-0 items-center gap-1.5">
            <AppLogoIcon large={large} className={className} alt="" />
            <MontanosaLogoIcon className={cn(className, secondClassName)} alt="" />
        </span>
    );
}
