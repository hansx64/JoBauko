<?php

namespace Database\Seeders;

use App\Enums\SkillType;
use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The starting skills dictionary. It powers skill suggestions, resume skill
 * detection and skill matching. Seekers and organizations can add skills
 * that aren't listed.
 */
class SkillSeeder extends Seeder
{
    private const TECHNICAL = [
        // Agriculture and livelihood
        'Highland Vegetable Farming', 'Organic Farming', 'Rice Farming', 'Crop Production', 'Vegetable Production',
        'Coffee Production', 'Horticulture', 'Agricultural Machinery Operation', 'Animal Production', 'Poultry Raising',
        'Swine Raising', 'Fish Culture', 'Food Processing', 'Weaving', 'Handicraft Making', 'Wood Carving',
        'Dressmaking', 'Sewing',
        // Trades
        'Carpentry', 'Masonry', 'Plumbing', 'Welding', 'Electrical Installation', 'Solar PV Installation',
        'Automotive Servicing', 'Motorcycle Servicing', 'Heavy Equipment Operation', 'Driving', 'Construction',
        'Surveying', 'Drafting', 'AutoCAD', 'Electronics Servicing',
        // Food, tourism and services
        'Cooking', 'Baking', 'Bread and Pastry Production', 'Food and Beverage Services', 'Housekeeping', 'Tour Guiding',
        'Hairdressing', 'Massage Therapy', 'Event Management', 'Security Services',
        // Health, care and education
        'Nursing', 'Caregiving', 'First Aid', 'Midwifery', 'Teaching', 'Tutoring', 'Early Childhood Care',
        // Office, business and sales
        'Microsoft Office', 'Microsoft Excel', 'Microsoft Word', 'Data Entry', 'Bookkeeping', 'Accounting',
        'Customer Service', 'Sales', 'Marketing', 'Inventory Management', 'Cashiering', 'Retail Operations',
        'Office Administration', 'Records Management',
        // IT and digital
        'Computer Hardware Servicing', 'Computer Programming', 'Web Development', 'PHP', 'JavaScript', 'Laravel', 'React',
        'MySQL', 'Python', 'Networking', 'Technical Support', 'Graphic Design', 'Social Media Management', 'Video Editing',
        'Photography', 'Content Writing',
    ];

    private const SOFT = [
        'Communication', 'Teamwork', 'Leadership', 'Problem Solving', 'Time Management', 'Adaptability',
        'Critical Thinking', 'Attention to Detail', 'Creativity', 'Public Speaking', 'Negotiation',
        'Conflict Resolution', 'Work Ethic', 'Organizational Skills',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([SkillType::Technical->value => self::TECHNICAL, SkillType::Soft->value => self::SOFT] as $type => $names) {
            foreach ($names as $name) {
                Skill::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'type' => $type]);
            }
        }
    }
}
