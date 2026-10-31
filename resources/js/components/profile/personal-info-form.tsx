import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import { FieldInput, type FieldDef } from '@/components/profile/entry-section';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { type PersonalInfo, type ProfileOptions } from '@/types/profile';

type PersonalForm = Record<keyof PersonalInfo, string>;

export function PersonalInfoForm({ personal, options }: { personal: PersonalInfo; options: ProfileOptions }) {
    const form = useForm<PersonalForm>({
        classification: personal.classification,
        date_of_birth: personal.date_of_birth ?? '',
        gender: personal.gender ?? '',
        address_line: personal.address_line ?? '',
        highest_education: personal.highest_education ?? '',
        current_education_status: personal.current_education_status ?? '',
        summary: personal.summary ?? '',
    });

    const fields: FieldDef[] = [
        {
            name: 'classification',
            label: 'Current status',
            type: 'select',
            required: true,
            options: options.classifications,
            hint: 'Update this whenever your situation changes. It helps us suggest the right opportunities.',
        },
        { name: 'date_of_birth', label: 'Date of birth', type: 'date' },
        { name: 'gender', label: 'Gender', type: 'select', options: options.genders },
        {
            name: 'address_line',
            label: 'Street / sitio',
            type: 'text',
            placeholder: 'e.g. Sitio Lamagan',
            hint: 'Your barangay is set in account settings.',
        },
        { name: 'highest_education', label: 'Highest educational attainment', type: 'select', options: options.educationLevels },
        { name: 'current_education_status', label: 'Current educational status', type: 'select', options: options.educationStatuses },
    ];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('seeker.profile.update'), { preserveScroll: true });
    };

    return (
        <Card id="personal" className="scroll-mt-20">
            <CardHeader>
                <CardTitle className="text-base">Personal information</CardTitle>
                <CardDescription>Basic details used for matching and shown to organizations you apply to.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {fields.map((field) => (
                            <FieldInput
                                key={field.name}
                                idPrefix="personal"
                                field={field}
                                value={form.data[field.name as keyof PersonalForm]}
                                error={form.errors[field.name as keyof PersonalForm]}
                                onChange={(value) => form.setData(field.name as keyof PersonalForm, String(value))}
                                disabled={form.processing}
                            />
                        ))}
                    </div>
                    <FieldInput
                        idPrefix="personal"
                        field={{
                            name: 'summary',
                            label: 'About me',
                            type: 'textarea',
                            placeholder: 'A short introduction: what you do and what you are looking for.',
                        }}
                        value={form.data.summary}
                        error={form.errors.summary}
                        onChange={(value) => form.setData('summary', String(value))}
                        disabled={form.processing}
                    />
                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Save
                        </Button>
                        <Transition
                            show={form.recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="text-muted-foreground text-sm">Saved</p>
                        </Transition>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
