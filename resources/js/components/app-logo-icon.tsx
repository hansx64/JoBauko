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
