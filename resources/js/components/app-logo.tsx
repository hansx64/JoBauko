import { LogoPair } from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            {/* The collapsed sidebar only has room for the seal. */}
            <LogoPair className="size-8" secondClassName="group-data-[collapsible=icon]:hidden" />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold">JoBauko</span>
                <span className="text-muted-foreground truncate text-xs leading-tight">Municipality of Bauko</span>
            </div>
        </>
    );
}
