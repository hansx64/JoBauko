import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, BellRing, Building2, FileCheck2, MapPinned, Search, ShieldCheck, Sparkles, UserRoundPlus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import AppLogoIcon from '@/components/app-logo-icon';
import { categoryIcons, categoryTones, OpportunityCard } from '@/components/opportunities/opportunity-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PublicLayout from '@/layouts/public-layout';
import { cn } from '@/lib/utils';
import { type OpportunityCardData } from '@/types/opportunity';

interface WelcomeProps {
    categories: { value: 'job' | 'scholarship' | 'livelihood' | 'training'; label: string; open: number }[];
    latest: OpportunityCardData[];
    featuredOrganizations: { id: number; name: string; type: string; logo_url: string | null; open_count: number }[];
    totals: { open: number; organizations: number };
}

const categoryBlurbs = {
    job: 'Full-time, part-time, contractual and internship work from employers in and around Bauko.',
    scholarship: 'Educational assistance from the LGU, agencies, schools and private sponsors.',
    livelihood: 'Starter kits, farm inputs, cash grants and other support to start or grow a livelihood.',
    training: 'Skills trainings, seminars and workshops to learn a trade or earn a certificate.',
};

const steps = [
    {
        icon: UserRoundPlus,
        title: 'Create your account',
        body: 'Sign up with your barangay and tell us whether you are a student, job seeker or entrepreneur.',
    },
    { icon: FileCheck2, title: 'Build your profile', body: 'Add your education and skills, or upload your resume and confirm what we read from it.' },
    { icon: Sparkles, title: 'Get matched', body: 'See opportunities that fit you, with a clear explanation of why each one was recommended.' },
    { icon: BellRing, title: 'Apply and stay updated', body: 'Apply online, track your applications and choose which updates you receive by SMS.' },
];

const benefits = [
    { icon: MapPinned, title: 'Made for Bauko', body: 'Opportunities for residents of all 22 barangays, in one place.' },
    { icon: ShieldCheck, title: 'Verified organizations', body: 'Every organization is checked by the JoBauko administrator before it can post.' },
    {
        icon: Sparkles,
        title: 'Recommendations you can understand',
        body: 'Each match shows the reasons behind it. You can always browse and apply to anything.',
    },
    { icon: BellRing, title: 'Your data, your choice', body: 'Protected under the Data Privacy Act. SMS updates only with your consent.' },
];

export default function Welcome({ categories, latest, featuredOrganizations, totals }: WelcomeProps) {
    const [query, setQuery] = useState('');

    const search: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('opportunities.index'), query.trim() ? { q: query.trim() } : {});
    };

    return (
        <PublicLayout>
            <Head title="Discover Opportunities. Build Your Future." />

            {/* Hero */}
            <section className="relative overflow-hidden bg-gradient-to-br from-[#006b31] via-[#00592a] to-[#0d2418] text-white">
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.07]"
                    style={{ backgroundImage: 'radial-gradient(circle at 1px 1px, white 1px, transparent 0)', backgroundSize: '22px 22px' }}
                />
                <div className="relative mx-auto grid max-w-6xl gap-10 px-4 py-16 md:py-24">
                    <AppLogoIcon
                        large
                        className="pointer-events-none absolute top-16 right-4 hidden size-56 shadow-2xl ring-8 ring-white/15 lg:block xl:size-64"
                    />
                    <div className="max-w-3xl space-y-6">
                        <p className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-sm ring-1 ring-white/20">
                            <MapPinned className="h-4 w-4" /> Municipality of Bauko, Mountain Province
                        </p>
                        <h1 className="text-4xl font-bold tracking-tight text-balance md:text-6xl">Discover Opportunities. Build Your Future.</h1>
                        <p className="max-w-2xl text-lg text-white/85 md:text-xl">
                            Find jobs, scholarships, livelihood programs and trainings that match your skills, qualifications, and aspirations.
                        </p>
                    </div>

                    <form onSubmit={search} className="flex max-w-2xl flex-col gap-2 rounded-xl bg-white p-2 shadow-xl sm:flex-row">
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-slate-400" />
                            {/* The search card is white in both themes, so the field's colors are fixed too. */}
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search by title, organization or skill"
                                aria-label="Search opportunities"
                                className="h-12 border-0 bg-white pl-10 text-base text-slate-900 caret-slate-900 [color-scheme:light] shadow-none placeholder:text-slate-500 focus-visible:ring-0"
                            />
                        </div>
                        <Button type="submit" size="lg" className="h-12 px-6">
                            Search
                        </Button>
                    </form>

                    <div className="grid max-w-2xl gap-3 sm:grid-cols-3">
                        {[
                            { href: route('opportunities.index'), label: 'Explore Opportunities' },
                            { href: route('register'), label: 'Create an Account' },
                            { href: route('organizations.register'), label: 'Post an Opportunity' },
                        ].map(({ href, label }) => (
                            <Button key={label} asChild size="lg" className="bg-white text-[#006b31] hover:bg-white/90">
                                <Link href={href}>{label}</Link>
                            </Button>
                        ))}
                    </div>

                    <dl className="flex flex-wrap gap-8 text-sm">
                        <div>
                            <dt className="text-white/75">Open opportunities</dt>
                            <dd className="text-3xl font-semibold tabular-nums">{totals.open}</dd>
                        </div>
                        <div>
                            <dt className="text-white/75">Verified organizations</dt>
                            <dd className="text-3xl font-semibold tabular-nums">{totals.organizations}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            {/* Categories */}
            <section className="mx-auto max-w-6xl px-4 py-16">
                <SectionHeading title="What are you looking for?" subtitle="Four kinds of opportunities, one place to find them." />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {categories.map((category) => {
                        const Icon = categoryIcons[category.value];
                        return (
                            <Link
                                key={category.value}
                                href={route('opportunities.index', { category: category.value })}
                                className="group bg-card hover:border-primary/40 rounded-xl border p-6 shadow-xs transition-all hover:shadow-md"
                            >
                                <span className={cn('mb-4 flex size-12 items-center justify-center rounded-xl', categoryTones[category.value])}>
                                    <Icon className="h-6 w-6" />
                                </span>
                                <h3 className="text-lg font-semibold">{category.label}</h3>
                                <p className="text-muted-foreground mt-1 text-sm">{categoryBlurbs[category.value]}</p>
                                <p className="text-primary mt-4 flex items-center gap-1 text-sm font-medium">
                                    {category.open} open now <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </p>
                            </Link>
                        );
                    })}
                </div>
            </section>

            {/* Latest */}
            <section className="bg-muted/40 border-y">
                <div className="mx-auto max-w-6xl px-4 py-16">
                    <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                        <SectionHeading title="Latest opportunities" subtitle="Recently published and open for applications." className="mb-0" />
                        <Button asChild variant="outline">
                            <Link href={route('opportunities.index')}>
                                View all <ArrowRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                    {latest.length === 0 ? (
                        <div className="bg-card text-muted-foreground rounded-xl border p-10 text-center">
                            No opportunities are open right now. New postings from verified organizations will appear here.
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {latest.map((opportunity) => (
                                <OpportunityCard key={opportunity.id} opportunity={opportunity} />
                            ))}
                        </div>
                    )}
                </div>
            </section>

            {/* How it works */}
            <section className="mx-auto max-w-6xl px-4 py-16">
                <SectionHeading title="How it works" subtitle="From sign-up to application in four steps." />
                <ol className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {steps.map((step, index) => (
                        <li key={step.title} className="space-y-3">
                            <div className="flex items-center gap-3">
                                <span className="bg-primary text-primary-foreground flex size-10 items-center justify-center rounded-full font-semibold">
                                    {index + 1}
                                </span>
                                <step.icon className="text-primary h-5 w-5" />
                            </div>
                            <h3 className="font-semibold">{step.title}</h3>
                            <p className="text-muted-foreground text-sm">{step.body}</p>
                        </li>
                    ))}
                </ol>
            </section>

            {/* Featured organizations */}
            <section className="bg-muted/40 border-y">
                <div className="mx-auto max-w-6xl px-4 py-16">
                    <SectionHeading title="Featured organizations" subtitle="Verified organizations with the most open opportunities." />
                    {featuredOrganizations.length === 0 ? (
                        <div className="bg-card text-muted-foreground rounded-xl border p-10 text-center">
                            Verified organizations with open opportunities will be featured here.
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {featuredOrganizations.map((organization) => (
                                <Link
                                    key={organization.id}
                                    href={route('opportunities.index', { organization: organization.id })}
                                    className="bg-card hover:border-primary/40 flex items-center gap-4 rounded-xl border p-4 transition-colors"
                                >
                                    {organization.logo_url ? (
                                        <img src={organization.logo_url} alt="" className="size-12 rounded-lg border object-cover" />
                                    ) : (
                                        <span className="bg-primary/10 text-primary flex size-12 items-center justify-center rounded-lg">
                                            <Building2 className="h-6 w-6" />
                                        </span>
                                    )}
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{organization.name}</p>
                                        <p className="text-muted-foreground text-sm">
                                            {organization.type} · {organization.open_count} open
                                        </p>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </section>

            {/* Benefits */}
            <section className="mx-auto max-w-6xl px-4 py-16">
                <SectionHeading title="Why JoBauko" subtitle="Built for the community, with your privacy in mind." />
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {benefits.map((benefit) => (
                        <div key={benefit.title} className="space-y-2">
                            <span className="bg-accent text-accent-foreground flex size-10 items-center justify-center rounded-lg">
                                <benefit.icon className="h-5 w-5" />
                            </span>
                            <h3 className="font-semibold">{benefit.title}</h3>
                            <p className="text-muted-foreground text-sm">{benefit.body}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* Call to action */}
            <section className="mx-auto max-w-6xl px-4 pb-16">
                {/* Fixed seal greens (not theme tokens) so the white text reads the same in light and dark mode. */}
                <div className="grid gap-6 overflow-hidden rounded-2xl bg-gradient-to-br from-[#006b31] to-[#0d2418] p-8 text-white md:grid-cols-[1fr_auto] md:items-center md:p-12">
                    <div className="space-y-2">
                        <h2 className="text-2xl font-bold md:text-3xl">Your next opportunity could be posted today.</h2>
                        <p className="text-white/80">Create a free account to get recommendations and apply online.</p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Button asChild size="lg" className="bg-white text-[#006b31] hover:bg-white/90">
                            <Link href={route('register')}>Create an Account</Link>
                        </Button>
                        <Button
                            asChild
                            size="lg"
                            variant="outline"
                            className="border-white/40 bg-transparent text-white hover:bg-white/10 hover:text-white"
                        >
                            <Link href={route('organizations.register')}>Post an Opportunity</Link>
                        </Button>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}

function SectionHeading({ title, subtitle, className }: { title: string; subtitle: string; className?: string }) {
    return (
        <div className={cn('mb-8 space-y-1', className)}>
            <h2 className="text-2xl font-bold tracking-tight md:text-3xl">{title}</h2>
            <p className="text-muted-foreground">{subtitle}</p>
        </div>
    );
}
