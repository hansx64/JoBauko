import { Plus, X } from 'lucide-react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface StringListEditorProps {
    items: string[];
    onChange: (items: string[]) => void;
    placeholder: string;
    addLabel: string;
    max?: number;
    errors?: Record<string, string>;
    errorPrefix: string;
    datalistId?: string;
}

/**
 * Edit a list of short text items (requirements, eligible courses, ...).
 */
export function StringListEditor({ items, onChange, placeholder, addLabel, max = 30, errors = {}, errorPrefix, datalistId }: StringListEditorProps) {
    return (
        <div className="grid gap-2">
            {items.map((item, index) => (
                <div key={index}>
                    <div className="flex gap-2">
                        <Input
                            value={item}
                            list={datalistId}
                            onChange={(e) => onChange(items.map((value, i) => (i === index ? e.target.value : value)))}
                            placeholder={placeholder}
                            aria-label={`${placeholder} ${index + 1}`}
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => onChange(items.filter((_, i) => i !== index))}
                            aria-label="Remove"
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </div>
                    <InputError message={errors[`${errorPrefix}.${index}`]} />
                </div>
            ))}
            <div>
                <Button type="button" variant="outline" size="sm" onClick={() => onChange([...items, ''])} disabled={items.length >= max}>
                    <Plus className="h-4 w-4" /> {addLabel}
                </Button>
            </div>
        </div>
    );
}
