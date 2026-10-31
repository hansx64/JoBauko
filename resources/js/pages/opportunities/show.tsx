import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    CalendarClock,
    CircleCheck,
    CircleDashed,
    FileText,
    GraduationCap,
    Mail,
    MapPin,
    Phone,
    Sparkles,
    Users,
    Wallet,
} from 'lucide-react';
import { ReactNode } from 'react';

import { matchLabel } from '@/components/opportunities/match-badge';
import { categoryIcons, categoryTones, deadlineText } from '@/components/opportunities/opportunity-card';
import { SaveButton } from '@/components/opportunities/save-button';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import PublicLayout from '@/layouts/public-layout';
import { cn } from '@/lib/utils';
import { type OpportunityCardData } from '@/types/opportunity';
import { formatDate } from '@/types/organization';

interface OpportunityDetails extends OpportunityCardData {
    description: string | null;
    qualifications: string | null;
    benefits: string | null;
    application_instructions: string | null;
    min_education: string | null;
    application_start: string | null;
    published_at: string | null;
    contact_email: string | null;
    contact_number: string | null;
    requirements: string[];
    documents: { name: string; is_required: boolean }[];
    skills: string[];
    job: { employment_type: string; work_setup: string | null; min_experience_years: number | null } | null;
    scholarship: {
        education_level: string;
        eligible_courses: string[];
        academic_requirements: string | null;
        financial_requirements: string | null;
    } | null;
    livelihood: {
        assistance_type: string;
        target_beneficiaries: string | null;
        assistance_provided: string | null;
        training_requirements: string | null;
    } | null;
    organization_details: { type: string; description: string | null; official_email: string; contact_number: string; office_address: string };
}

export default function ShowOpportunity({ opportunity, isSeeker }: { opportunity: OpportunityDetails; isSeeker: boolean }) {
    const Icon = categoryIcons[opportunity.category];
    const match = opportunity.match;

    return (
        <PublicLayout>
            <Head title={opportunity.title} />
            <div className="mx-auto max-w-6xl px-4 py-8">
                <Link
                    href={route('opportunities.index')}
                    className="text-muted-foreground hover:text-foreground mb-6 inline-flex items-center gap-1 text-sm"
                >
                    <ArrowLeft className="h-4 w-4" /> All opportunities
                </Link>

                <div className="grid items-start gap-8 lg:grid-cols-[1fr_22rem]">
                    <div className="grid min-w-0 gap-6">
                        <header className="space-y-4">
                            <div className="flex items-center gap-3">
                                {opportunity.organization.logo_url ? (
                                    <img src={opportunity.organization.logo_url} alt="" className="size-14 rounded-xl border object-cover" />
                                ) : (
                                    <span className={cn('flex size-14 items-center justify-center rounded-xl', categoryTones[opportunity.category])}>
                                        <Icon className="h-7 w-7" />
                                    </span>
                                )}
                                <div>
                                    <p className="font-medium">{opportunity.organization.name}</p>
                                    <p className="text-muted-foreground text-sm">{opportunity.organization_details.type}</p>
                                </div>
                            </div>
                            <h1 className="text-3xl font-bold tracking-tight text-balance">{opportunity.title}</h1>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-medium', categoryTones[opportunity.category])}>
                                    {opportunity.category_label}
                                </span>
                                {opportunity.employment_type && <Badge variant="secondary">{opportunity.employment_type}</Badge>}
                                <StatusBadge
                                    status={opportunity.status}
                                    label={opportunity.accepting ? 'Accepting applications' : opportunity.status_label}
                                />
                            </div>
                        </header>

                        <div className="grid gap-3 rounded-xl border p-4 text-sm sm:grid-cols-2">
                            <Fact icon={MapPin} label="Location">
                                {opportunity.location}
                            </Fact>
                            <Fact icon={CalendarClock} label="Deadline">
                                {deadlineText(opportunity)}
                            </Fact>
                            {opportunity.salary && (
                                <Fact icon={Wallet} label="Salary">
                                    {opportunity.salary}
                                </Fact>
                            )}
                            {opportunity.slots && (
                                <Fact icon={Users} label="Available slots">
                                    {opportunity.slots}
                                </Fact>
                            )}
                            {opportunity.min_education && (
                                <Fact icon={GraduationCap} label="Educational requirement">
                                    {opportunity.min_education}
                                </Fact>
                            )}
                            {opportunity.application_start && (
                                <Fact icon={CalendarClock} label="Applications open">
                                    {formatDate(opportunity.application_start)}
                                </Fact>
                            )}
                        </div>

                        {opportunity.description && (
                            <Section title="About this opportunity">
                                <Prose>{opportunity.description}</Prose>
                            </Section>
                        )}

                        {opportunity.job && (
                            <Section title="Job details">
                                <dl className="grid gap-3 text-sm sm:grid-cols-3">
                                    <Detail label="Employment type">{opportunity.job.employment_type}</Detail>
                                    {opportunity.job.work_setup && <Detail label="Work setup">{opportunity.job.work_setup}</Detail>}
                                    {opportunity.job.min_experience_years !== null && (
                                        <Detail label="Experience">
                                            {opportunity.job.min_experience_years === 0
                                                ? 'No experience required'
                                                : `${opportunity.job.min_experience_years}+ years`}
                                        </Detail>
                                    )}
                                </dl>
                            </Section>
                        )}

                        {opportunity.scholarship && (
                            <Section title="Scholarship details">
                                <dl className="grid gap-4 text-sm">
                                    <Detail label="For students in">{opportunity.scholarship.education_level}</Detail>
                                    {opportunity.scholarship.eligible_courses.length > 0 && (
                                        <Detail label="Eligible courses">
                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                {opportunity.scholarship.eligible_courses.map((course) => (
                                                    <Badge key={course} variant="outline" className="font-normal">
                                                        {course}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </Detail>
                                    )}
                                    {opportunity.scholarship.academic_requirements && (
                                        <Detail label="Academic requirements">
                                            <Prose>{opportunity.scholarship.academic_requirements}</Prose>
                                        </Detail>
                                    )}
                                    {opportunity.scholarship.financial_requirements && (
                                        <Detail label="Financial requirements">
                                            <Prose>{opportunity.scholarship.financial_requirements}</Prose>
                                        </Detail>
                                    )}
                                </dl>
                            </Section>
                        )}

                        {opportunity.livelihood && (
                            <Section title="Program details">
                                <dl className="grid gap-4 text-sm">
                                    <Detail label="Type of assistance">{opportunity.livelihood.assistance_type}</Detail>
                                    {opportunity.livelihood.target_beneficiaries && (
                                        <Detail label="Target beneficiaries">
                                            <Prose>{opportunity.livelihood.target_beneficiaries}</Prose>
                                        </Detail>
                                    )}
                                    {opportunity.livelihood.assistance_provided && (
                                        <Detail label="Assistance provided">
                                            <Prose>{opportunity.livelihood.assistance_provided}</Prose>
                                        </Detail>
                                    )}
                                    {opportunity.livelihood.training_requirements && (
                                        <Detail label="Training requirements">
                                            <Prose>{opportunity.livelihood.training_requirements}</Prose>
                                        </Detail>
                                    )}
                                </dl>
                            </Section>
                        )}

                        {(opportunity.qualifications || opportunity.requirements.length > 0 || opportunity.skills.length > 0) && (
                            <Section title="Qualifications and requirements">
                                {opportunity.qualifications && <Prose>{opportunity.qualifications}</Prose>}
                                {opportunity.requirements.length > 0 && (
                                    <ul className="list-disc space-y-1 pl-5 text-sm">
                                        {opportunity.requirements.map((requirement) => (
                                            <li key={requirement}>{requirement}</li>
                                        ))}
                                    </ul>
                                )}
                                {opportunity.skills.length > 0 && (
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">Skills</p>
                                        <div className="flex flex-wrap gap-1.5">
                                            {opportunity.skills.map((skill) => (
                                                <Badge key={skill} variant="secondary" className="font-normal">
                                                    {skill}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </Section>
                        )}

                        {opportunity.documents.length > 0 && (
                            <Section title="Documents to prepare">
                                <ul className="grid gap-2 text-sm">
                                    {opportunity.documents.map((document) => (
                                        <li key={document.name} className="flex items-center gap-2">
                                            <FileText className="text-muted-foreground h-4 w-4" />
                                            {document.name}
                                            <span className="text-muted-foreground">{document.is_required ? '(required)' : '(optional)'}</span>
                                        </li>
                                    ))}
                                </ul>
                            </Section>
                        )}

                        {opportunity.benefits && (
                            <Section title={opportunity.category === 'job' ? 'Benefits and compensation' : 'Benefits'}>
                                <Prose>{opportunity.benefits}</Prose>
                            </Section>
                        )}

                        {opportunity.application_instructions && (
                            <Section title="How to apply">
                                <Prose>{opportunity.application_instructions}</Prose>
                            </Section>
                        )}
                    </div>

                    <aside className="grid gap-4 lg:sticky lg:top-20">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {opportunity.accepting ? 'Accepting applications' : 'Not accepting applications'}
                                </CardTitle>
                                <CardDescription>{deadlineText(opportunity)}</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {isSeeker ? (
                                    <SaveButton opportunityId={opportunity.id} saved={opportunity.saved} size="default" />
                                ) : (
                                    <Button asChild variant="outline">
                                        <Link href={route('login')}>Log in to save and see your match</Link>
                                    </Button>
                                )}
                                {(opportunity.contact_email || opportunity.contact_number) && (
                                    <div className="text-muted-foreground grid gap-1 text-sm">
                                        {opportunity.contact_email && (
                                            <a href={`mailto:${opportunity.contact_email}`} className="hover:text-foreground flex items-center gap-2">
                                                <Mail className="h-4 w-4" /> {opportunity.contact_email}
                                            </a>
                                        )}
                                        {opportunity.contact_number && (
                                            <span className="flex items-center gap-2">
                                                <Phone className="h-4 w-4" /> {opportunity.contact_number}
                                            </span>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {match && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center justify-between gap-2 text-base">
                                        <span className="flex items-center gap-2">
                                            <Sparkles className="h-4 w-4 text-teal-600" /> Your match
                                        </span>
                                        <span className="text-2xl font-semibold tabular-nums">{match.score}%</span>
                                    </CardTitle>
                                    <CardDescription>
                                        {matchLabel(match.score)}. Based on your profile; this is only a guide, not an eligibility decision.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 text-sm">
                                    {match.reasons.length > 0 && (
                                        <div className="grid gap-1.5">
                                            <p className="font-medium">Why it matches</p>
                                            <ul className="grid gap-1.5">
                                                {match.reasons.map((reason) => (
                                                    <li key={reason} className="flex gap-2">
                                                        <CircleCheck className="mt-0.5 h-4 w-4 shrink-0 text-teal-600" />
                                                        {reason}
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                    {match.gaps && match.gaps.length > 0 && (
                                        <div className="grid gap-1.5">
                                            <p className="font-medium">To improve your match</p>
                                            <ul className="text-muted-foreground grid gap-1.5">
                                                {match.gaps.map((gap) => (
                                                    <li key={gap} className="flex gap-2">
                                                        <CircleDashed className="mt-0.5 h-4 w-4 shrink-0" />
                                                        {gap}
                                                    </li>
                                                ))}
                                            </ul>
                                            <Link href={route('seeker.profile.edit')} className="text-primary font-medium hover:underline">
                                                Update my profile →
                                            </Link>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Building2 className="h-4 w-4" /> {opportunity.organization.name}
                                </CardTitle>
                                {opportunity.organization_details.description && (
                                    <CardDescription className="line-clamp-4">{opportunity.organization_details.description}</CardDescription>
                                )}
                            </CardHeader>
                            <CardContent className="text-muted-foreground grid gap-1 text-sm">
                                <span className="flex items-start gap-2">
                                    <MapPin className="mt-0.5 h-4 w-4 shrink-0" /> {opportunity.organization_details.office_address}
                                </span>
                                <a
                                    href={`mailto:${opportunity.organization_details.official_email}`}
                                    className="hover:text-foreground flex items-center gap-2"
                                >
                                    <Mail className="h-4 w-4" /> {opportunity.organization_details.official_email}
                                </a>
                                <span className="flex items-center gap-2">
                                    <Phone className="h-4 w-4" /> {opportunity.organization_details.contact_number}
                                </span>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>
        </PublicLayout>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-3">
            <h2 className="text-lg font-semibold">{title}</h2>
            {children}
        </section>
    );
}

function Prose({ children }: { children: string }) {
    return <p className="text-sm leading-relaxed whitespace-pre-line">{children}</p>;
}

function Fact({ icon: Icon, label, children }: { icon: typeof MapPin; label: string; children: ReactNode }) {
    return (
        <div className="flex items-start gap-2">
            <Icon className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
            <div>
                <p className="text-muted-foreground text-xs">{label}</p>
                <p className="font-medium">{children}</p>
            </div>
        </div>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5">{children}</dd>
        </div>
    );
}
