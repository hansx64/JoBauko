import { Head } from '@inertiajs/react';

import { CompletionCard } from '@/components/profile/completion-card';
import { EntrySection, type FieldDef } from '@/components/profile/entry-section';
import { InterestsEditor } from '@/components/profile/interests-editor';
import { PersonalInfoForm } from '@/components/profile/personal-info-form';
import { ResumeCard } from '@/components/profile/resume-card';
import { SkillsEditor } from '@/components/profile/skills-editor';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import {
    type CertificationEntry,
    type Completion,
    type EducationEntry,
    type ExperienceEntry,
    type Interest,
    type PersonalInfo,
    type ProfileOptions,
    type ResumeSummary,
    type TrainingEntry,
} from '@/types/profile';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My profile', href: '/profile' }];

interface ProfilePageProps {
    personal: PersonalInfo;
    education: EducationEntry[];
    experience: ExperienceEntry[];
    certifications: CertificationEntry[];
    trainings: TrainingEntry[];
    skills: string[];
    interests: Interest[];
    resume: ResumeSummary | null;
    completion: Completion;
    skillSuggestions: string[];
    options: ProfileOptions;
}

function formatMonth(date: string | null) {
    return date ? new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', year: 'numeric' }) : null;
}

export default function Profile(props: ProfilePageProps) {
    const { personal, education, experience, certifications, trainings, skills, interests, resume, completion, skillSuggestions, options } = props;

    const educationFields: FieldDef[] = [
        { name: 'level', label: 'Level', type: 'select', required: true, options: options.educationLevels },
        { name: 'school', label: 'School', type: 'text', required: true, placeholder: 'e.g. Mountain Province State University' },
        { name: 'course', label: 'Course, strand or program', type: 'text', placeholder: 'e.g. BS Information Technology' },
        { name: 'year_graduated', label: 'Year graduated', type: 'number', hint: 'If still studying, the expected year.' },
        { name: 'is_current', label: 'I am currently studying here', type: 'checkbox' },
    ];

    const experienceFields: FieldDef[] = [
        { name: 'position', label: 'Position or role', type: 'text', required: true, placeholder: 'e.g. Sales Clerk' },
        { name: 'company', label: 'Company, employer or farm', type: 'text', required: true },
        { name: 'industry', label: 'Industry', type: 'text', placeholder: 'e.g. Retail, Agriculture' },
        { name: 'start_date', label: 'Start date', type: 'date', required: true },
        { name: 'end_date', label: 'End date', type: 'date', hint: 'Leave empty if you still work here.' },
        { name: 'description', label: 'What you did', type: 'textarea' },
    ];

    const certificationFields: FieldDef[] = [
        { name: 'name', label: 'Certification or license', type: 'text', required: true, placeholder: 'e.g. Bread and Pastry Production NC II' },
        { name: 'issuer', label: 'Issued by', type: 'text', placeholder: 'e.g. TESDA' },
        { name: 'issued_at', label: 'Date issued', type: 'date' },
        { name: 'expires_at', label: 'Expiry date', type: 'date' },
    ];

    const trainingFields: FieldDef[] = [
        { name: 'title', label: 'Training or seminar', type: 'text', required: true },
        { name: 'provider', label: 'Conducted by', type: 'text', required: true, placeholder: 'e.g. DTI, ATI, TESDA' },
        { name: 'completed_at', label: 'Date completed', type: 'date', required: true },
        { name: 'hours', label: 'Number of hours', type: 'number', required: true },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My profile" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">My profile</h1>
                    <p className="text-muted-foreground">Keep your profile up to date to get better opportunity recommendations.</p>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[1fr_20rem]">
                    <div className="grid min-w-0 gap-6">
                        <ResumeCard resume={resume} />
                        <PersonalInfoForm personal={personal} options={options} />

                        <EntrySection<EducationEntry>
                            id="education"
                            title="Educational background"
                            description="Schools you attended, from most recent."
                            emptyText="No education added yet."
                            addLabel="Add education"
                            items={education}
                            fields={educationFields}
                            storeUrl={route('seeker.profile.education.store')}
                            itemUrl={(item) => route('seeker.profile.education.update', item.id)}
                            toForm={(item) => ({
                                level: item?.level ?? '',
                                school: item?.school ?? '',
                                course: item?.course ?? '',
                                year_graduated: item?.year_graduated ? String(item.year_graduated) : '',
                                is_current: item?.is_current ?? false,
                            })}
                            renderItem={(item) => (
                                <>
                                    <p className="font-medium">{item.course || item.level_label}</p>
                                    <p className="text-muted-foreground">
                                        {item.school} · {item.is_current ? `Currently studying` : (item.year_graduated ?? item.level_label)}
                                    </p>
                                </>
                            )}
                        />

                        <SkillsEditor skills={skills} suggestions={skillSuggestions} />

                        <EntrySection<ExperienceEntry>
                            id="experience"
                            title="Work experience"
                            description="Jobs, part-time work, farm work or volunteer work."
                            emptyText="No work experience added yet."
                            addLabel="Add experience"
                            items={experience}
                            fields={experienceFields}
                            storeUrl={route('seeker.profile.experience.store')}
                            itemUrl={(item) => route('seeker.profile.experience.update', item.id)}
                            toForm={(item) => ({
                                position: item?.position ?? '',
                                company: item?.company ?? '',
                                industry: item?.industry ?? '',
                                start_date: item?.start_date ?? '',
                                end_date: item?.end_date ?? '',
                                description: item?.description ?? '',
                            })}
                            renderItem={(item) => (
                                <>
                                    <p className="font-medium">{item.position}</p>
                                    <p className="text-muted-foreground">
                                        {item.company} · {formatMonth(item.start_date)} – {formatMonth(item.end_date) ?? 'Present'}
                                    </p>
                                    {item.description && (
                                        <p className="text-muted-foreground mt-1 line-clamp-2 whitespace-pre-line">{item.description}</p>
                                    )}
                                </>
                            )}
                        />

                        <EntrySection<CertificationEntry>
                            id="credentials"
                            title="Certifications and licenses"
                            description="e.g. TESDA National Certificates, civil service eligibility, professional licenses."
                            emptyText="No certifications added yet."
                            addLabel="Add certification"
                            items={certifications}
                            fields={certificationFields}
                            storeUrl={route('seeker.profile.certifications.store')}
                            itemUrl={(item) => route('seeker.profile.certifications.update', item.id)}
                            toForm={(item) => ({
                                name: item?.name ?? '',
                                issuer: item?.issuer ?? '',
                                issued_at: item?.issued_at ?? '',
                                expires_at: item?.expires_at ?? '',
                            })}
                            renderItem={(item) => (
                                <>
                                    <p className="font-medium">{item.name}</p>
                                    <p className="text-muted-foreground">
                                        {[item.issuer, formatMonth(item.issued_at)].filter(Boolean).join(' · ') || 'No details'}
                                    </p>
                                </>
                            )}
                        />

                        <EntrySection<TrainingEntry>
                            id="trainings"
                            title="Trainings attended"
                            description="Trainings, seminars and workshops."
                            emptyText="No trainings added yet."
                            addLabel="Add training"
                            items={trainings}
                            fields={trainingFields}
                            storeUrl={route('seeker.profile.trainings.store')}
                            itemUrl={(item) => route('seeker.profile.trainings.update', item.id)}
                            toForm={(item) => ({
                                title: item?.title ?? '',
                                provider: item?.provider ?? '',
                                completed_at: item?.completed_at ?? '',
                                hours: item?.hours ? String(item.hours) : '',
                            })}
                            renderItem={(item) => (
                                <>
                                    <p className="font-medium">{item.title}</p>
                                    <p className="text-muted-foreground">
                                        {[item.provider, item.hours ? `${item.hours} hours` : null, formatMonth(item.completed_at)]
                                            .filter(Boolean)
                                            .join(' · ') || 'No details'}
                                    </p>
                                </>
                            )}
                        />

                        <InterestsEditor interests={interests} categories={options.interestCategories} />
                    </div>

                    <aside className="lg:sticky lg:top-4">
                        <CompletionCard completion={completion} />
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
