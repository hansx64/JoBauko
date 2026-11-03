import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <AppLogoIcon className="size-8" alt="" />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold">JoBauko</span>
                <span className="text-muted-foreground truncate text-xs leading-tight">Municipality of Bauko</span>
            </div>
        </>
    );
}
