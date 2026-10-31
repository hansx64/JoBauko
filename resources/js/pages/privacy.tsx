import AppLogoIcon from '@/components/app-logo-icon';
import { Head, Link } from '@inertiajs/react';
import { type ReactNode } from 'react';

interface PrivacyProps {
    version: string;
    contactEmail: string;
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-3">
            <h2 className="text-lg font-semibold">{title}</h2>
            <div className="text-muted-foreground space-y-3 leading-relaxed">{children}</div>
        </section>
    );
}

export default function Privacy({ version, contactEmail }: PrivacyProps) {
    return (
        <div className="bg-background min-h-screen">
            <Head title="Privacy Notice" />
            <header className="border-b">
                <div className="mx-auto flex max-w-3xl items-center gap-2 px-4 py-4">
                    <Link href={route('home')} className="flex items-center gap-2 font-semibold">
                        <AppLogoIcon className="size-6 fill-current" />
                        JoBauko
                    </Link>
                </div>
            </header>

            <main className="mx-auto max-w-3xl space-y-8 px-4 py-10">
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold tracking-tight">Privacy Notice</h1>
                    <p className="text-muted-foreground text-sm">Version {version}</p>
                </div>

                <p className="leading-relaxed">
                    JoBauko connects residents of Bauko, Mountain Province with jobs, scholarships and livelihood programs. We respect your privacy
                    and process personal data in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and its implementing rules.
                </p>

                <Section title="What we collect">
                    <ul className="list-disc space-y-1 pl-5">
                        <li>Account details: full name, email address, mobile number, barangay and password (stored only in hashed form).</li>
                        <li>Your current status (for example Student or Unemployed) and the profile details you choose to add.</li>
                        <li>Resumes and supporting documents you upload.</li>
                        <li>Your applications, saved opportunities and notification preferences.</li>
                        <li>Technical records such as login times and IP addresses, used for security.</li>
                    </ul>
                </Section>

                <Section title="Why we use it">
                    <ul className="list-disc space-y-1 pl-5">
                        <li>To create and secure your account.</li>
                        <li>To recommend opportunities that match your profile and explain why they match.</li>
                        <li>To process applications you submit and keep you informed of their status.</li>
                        <li>To send notifications you have chosen to receive.</li>
                        <li>To produce statistics for program planning. Reports use aggregated figures, not individual records.</li>
                    </ul>
                </Section>

                <Section title="Who can see it">
                    <p>
                        Organizations can see your profile, resume and documents only for opportunities you apply to. Administrators can access
                        account information to operate and protect the platform. We do not sell your personal data or share it for marketing.
                    </p>
                </Section>

                <Section title="SMS notifications">
                    <p>
                        SMS notifications are optional and sent only with your consent. You choose which topics you receive and can withdraw consent
                        at any time. Account security messages, such as verification codes, are sent when you request them.
                    </p>
                </Section>

                <Section title="How we protect it">
                    <p>
                        Resumes and documents are stored privately and are never publicly accessible. Access is restricted by role, and important
                        actions are recorded in a protected audit log.
                    </p>
                </Section>

                <Section title="Retention">
                    <p>
                        We keep your data while your account is active. When you delete your account, your profile, resumes, applications and
                        preferences are permanently erased. Records that must be kept for accountability, such as audit logs, are retained without
                        your account link.
                    </p>
                </Section>

                <Section title="Your rights">
                    <p>
                        You have the right to be informed, to access, to correct, to object, to erasure or blocking, to data portability, and to file
                        a complaint with the National Privacy Commission. You can update most of your information yourself in your account settings,
                        or delete your account there.
                    </p>
                </Section>

                <Section title="Contact">
                    <p>
                        For privacy questions or requests, email{' '}
                        <a href={`mailto:${contactEmail}`} className="text-foreground underline underline-offset-4">
                            {contactEmail}
                        </a>
                        .
                    </p>
                </Section>
            </main>
        </div>
    );
}
