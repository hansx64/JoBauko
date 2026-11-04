# JoBauko full walkthrough video

A 3-minute video that introduces everything on the website. The 60-second pitch version is in [PITCH-VIDEO.md](PITCH-VIDEO.md).

Two parts:

1. **The prompt**: paste it into a new Claude Code session to have Claude build the video with Remotion.
2. **The script**: what you say while the video plays. The video's scenes are timed to it.

---

## 1. Prompt for Claude

Copy everything inside the box.

````text
Create a 3-minute motion graphics video that walks through everything on JoBauko, a website I built. Use the Remotion skills I have installed: read the Remotion best-practices and create skills before writing any code, and follow them.

The video plays behind me while I present. I speak over it, so:
- No voice-over. No music unless I ask for it later; leave an empty audio slot.
- On-screen text is short (headlines, labels and lists of a few words each, never paragraphs) so the audience can listen to me, not read.
- Each scene must start exactly at the time given below. My script is timed to these scenes.

## What JoBauko is

JoBauko is the opportunity portal of the Municipality of Bauko, Mountain Province, Philippines. It brings four kinds of opportunities into one website for residents of Bauko's 22 barangays:
- Jobs
- Scholarships
- Livelihood programs
- Seminars and trainings

It has three kinds of users:
- residents (job seekers, students and others)
- organizations that post opportunities
- the municipality, as the site administrator

It is built by Montanosa Tech for the Municipality of Bauko.

Live site: https://jobauko.onrender.com
The site runs on a free host that sleeps when idle. The first page load can take up to a minute. Wait for it and reload; it is not broken.

## Visit the site first

Before designing anything, open the live site and take screenshots of these public pages at 1920x1080:
- `/`: the landing page, including the hero, the "How it works" steps and the benefits.
- `/opportunities`: the listing with its filters. Also open the filters panel and screenshot it.
- One opportunity page of each kind, chosen from the list:
  - a job, such as "Front Desk Associate"
  - a scholarship, such as "Highlands College Scholarship Grant"
  - a livelihood program, such as "Swine Dispersal Program"
  - a training, such as "Housekeeping Skills Training"
- `/register` and `/organizations/register`
- `/login`
- `/privacy`

Use the screenshots for the look of the video (colors, fonts, the card style, and the red-and-black striped band at the top of the header, a Cordillera textile pattern). Show the public pages as real screenshots inside a clean browser frame.

Do not log in, create accounts or submit any form on the live site. It is a real site with real data. Show the signed-in screens (seeker, organization and admin) as clean, simplified motion-graphic recreations in the site's style. Use only the facts and labels in this prompt for them.

## Brand

- Primary green: #007a35. Hero gradient: #006b31 → #00592a → #0d2418. Bright green in dark areas: #3ecf7a. Text on green: white.
- Font: Instrument Sans (weights 400, 500, 600), from fonts.bunny.net or Google Fonts.
- Logos are both shown as circles with a white background:
  - The seal of the Municipality of Bauko: https://jobauko.onrender.com/images/bauko-seal.png
  - The Montanosa Tech logo: https://jobauko.onrender.com/images/montanosa-tech.png
  Download them into the Remotion project's public/ folder. Never redraw, recolor or crop either logo, especially the seal.
- Tagline from the site: "Discover Opportunities. Build Your Future."

## Format

- 1920x1080, 30 fps, exactly 3 minutes (5400 frames).
- Five chapters: For everyone, For residents, For organizations, For the municipality, and Trust and privacy. Show a short chapter title card (about 1 second) at the start of the first scene of each chapter.
- A thin progress bar along the bottom edge showing the current chapter name.
- Smooth transitions that end before the next scene's start time.
- Render to out/jobauko-walkthrough.mp4 (H.264). Also export a still of the final frame as out/jobauko-walkthrough-end.png.

## Scenes (start time → content)

### Opening

**Scene 1 · 0:00–0:08 · Opening**
Both circular logos side by side, then "JoBauko" and the tagline "Discover Opportunities. Build Your Future." Small label: "Municipality of Bauko, Mountain Province".

**Scene 2 · 0:08–0:18 · One portal, three users**
- The four category cards (Jobs · Scholarships · Livelihood programs · Seminars and trainings) gather into one website frame.
- Three icons appear below it: "Residents", "Organizations", "Municipality".
- Words: "22 barangays. One portal."

### Chapter: For everyone

**Scene 3 · 0:18–0:30 · Browse without an account**
- Landing page screenshot, then the /opportunities screenshot.
- Highlight the search box ("Search by title, organization or skill") and the category tabs (All · Job · Scholarship · Livelihood Program · Training / Seminar, exactly as labelled on the site).
- Then the real filters as chips: Location · Remote only · Organization · My education level · Skill · Employment type · Monthly salary · Application deadline.
- Then the real sort options: Best match for me · Newest first · Deadline soonest · Highest salary.
- Then one opportunity page per category, flipping quickly. Highlight what is different on each:
  - Job: salary and employment type
  - Scholarship: eligible courses
  - Livelihood program: assistance provided
  - Training: schedule and certificate
- End with a small flag icon labelled "Report a listing (signed-in users)".
- Words: "Browse and search, no account needed."

### Chapter: For residents

**Scene 4 · 0:30–0:40 · Sign up**
- Use the /register screenshot. Highlight the real field labels in turn: "Full name", "Email address", "Mobile number", "Barangay", "Current status".
- Show the password strength meter filling from red to green.
- Two check marks: "Email verified", "Mobile verified by SMS code".
- Words: "Sign up in minutes."

**Scene 5 · 0:40–0:52 · Your profile**
- A recreated profile page with these sections ticking complete one by one:
  - Personal information, Education, Work experience
  - Trainings, Certifications, Skills, Interests
- A completion card with a "Required to apply" badge on the required sections.
- Words: "One profile for every application."

**Scene 6 · 0:52–1:02 · Resume: upload or build**
- A file labelled "resume.pdf" (with a "PDF or DOCX" tag) drops in. Education and skills fill in. A "Confirm" button is pressed, because the resident reviews what was read before anything is saved.
- Then "No resume? Build one.": a clean one-page resume PDF slides out, with two toggles, "Show birth date" and "Show sex", both switched off. These are left out unless the resident chooses.
- Words: "Upload your resume, or build one."

**Scene 7 · 1:02–1:12 · Matching you can understand**
- An opportunity card with a match score ring filling to 82% and the site's real badge text "82% · Strong match".
- Beside it, the reasons with check marks, worded as the site words them:
  - "You have 3 of 4 listed skills"
  - "Your education meets the requirement"
  - "In your barangay, Poblacion"
- Then one line in a muted color: "Add your areas of interest to your profile.", the kind of tip the site gives to improve a match.
- Show the four labels the site uses as a small legend: Strong match · Good match · Possible match · Low match.
- Words: "Recommendations with the reasons why."
- Do not use the words AI, artificial intelligence or machine learning anywhere. The matching is rule-based and explainable.

**Scene 8 · 1:12–1:24 · Apply and track**
- An "Apply" button. A checklist:
  - "Profile complete"
  - "Resume attached (jobs only)"
  - "Required documents uploaded"
- A bookmark icon: "Save for later".
- A progress tracker moving through the real application statuses, in order: Submitted → Under review → Shortlisted → For interview → Approved.
- A small "Withdraw" button at the side.
- Words: "Apply online. Track every step."

**Scene 9 · 1:24–1:32 · Updates your way**
- Three channel chips: "In-app", "Email", "SMS".
- A settings panel with on/off switches for the site's real notification topics:
  - Job Opportunities, Scholarship Opportunities, Livelihood Opportunities, Training Programs
  - Application Status Updates, Application Deadlines, Important Announcements
- A small "SMS only with your consent" note.
- Words: "You choose what you hear about."

### Chapter: For organizations

**Scene 10 · 1:32–1:44 · Register and get verified**
- Use the /organizations/register screenshot.
- A logo and a document labelled "Business permit" upload into it.
- A status badge changes from "Pending" to "Verified" with a stamp.
- Words: "Every organization is verified before it can post."

**Scene 11 · 1:44–1:56 · Post opportunities**
- A "Post an opportunity" form with the four category tabs. Each tab shows its own fields:
  - Job: employment type and salary
  - Scholarship: eligible courses
  - Livelihood program: assistance type
  - Training: dates and schedule
- Below the form: "Required documents", "Slots", "Deadline".
- A status chip moving Draft → Published → Closed.
- Words: "Post any kind of opportunity."

**Scene 12 · 1:56–2:08 · Review applicants as a team**
- An applicant table with a "Match" column showing percentages.
- Open one applicant: their profile, their resume, and a "Remarks" note being added.
- A status dropdown showing Shortlisted, For interview, For further requirements, Approved, Rejected.
- Three team role chips with one short line each:
  - Admin: "everything"
  - Recruitment officer: "posts and decides"
  - Reviewer: "reviews and adds remarks"
- Words: "Hire as a team."

### Chapter: For the municipality

**Scene 13 · 2:08–2:20 · Oversight**
- An admin dashboard recreation with four tiles:
  - "Organizations": Verify, Reject, Suspend
  - "Opportunities": Suspend, Reinstate
  - "Listing reports": Action taken, Dismissed
  - "Users": Deactivate, Reactivate
- Words: "The municipality keeps the portal safe."

**Scene 14 · 2:20–2:30 · Reach every resident**
- An announcement being written and sent, with two channel checkboxes: "Email" and "SMS".
- Then three small panels: "SMS templates", "Delivery log", "Audit log".
- Words: "Announcements by email and SMS."

**Scene 15 · 2:30–2:42 · Analytics and reports**
- A grid of the eight real report titles:
  - Applications, Approved applicants, Geographic distribution, Opportunity categories
  - Organization activity, Organizations, Published opportunities, Registered users
- One opens: a bar chart labelled "By barangay" (decorative bars; no real numbers), a date range picker, and "Export PDF" and "Export Excel" buttons.
- Words: "Data the LGU can act on."

### Chapter: Trust and privacy

**Scene 16 · 2:42–2:52 · Trust and privacy**
Icons with short labels appearing one by one:
- "Data Privacy Act"
- "Consent for SMS"
- "Private uploaded files"
- "Delete your account anytime"
- "Light and dark mode"
- "Works on phones"
Words: "Your data, your choice."

**Scene 17 · 2:52–3:00 · Closing**
Both logos, "JoBauko", the tagline and "jobauko.onrender.com". Hold the final frame for at least the last 3 seconds.

## Accuracy rules: do not break these

Show only what is listed in this prompt. In particular, never show or say:
- AI, artificial intelligence, machine learning or ChatGPT
- a mobile app or app-store badges
- user counts, statistics, percentages of success or testimonials (the 82% in scene 7 is an example score, nothing more)
- pricing, "free" or "paid"
- social media logins
- any organization or person that is not in this prompt or on the live site
- real-looking numbers in charts, tables or dashboards (use bars without values, or placeholder dashes)

Spell the names exactly: "JoBauko" (capital J and B, one word), "Bauko", "Mountain Province", "Montanosa Tech", "barangay".

## When done

1. Watch the render frame by frame at every scene boundary (0:08, 0:18, 0:30, 0:40, 0:52, 1:02, 1:12, 1:24, 1:32, 1:44, 1:56, 2:08, 2:20, 2:30, 2:42, 2:52) and confirm each scene starts on time.
2. Check every word on screen against this prompt for spelling and against the accuracy rules.
3. Check that the logos are not stretched, cropped or recolored.
4. Then tell me where the MP4 is and list anything you could not do exactly as asked.
````

---

## 2. Script to say while the video plays

About 430 words, which takes about 3 minutes at a calm presenting pace. Each line starts when its scene appears. If you fall behind, drop the sentences in *[brackets]*.

### Opening

| Time | On screen | Say |
|---|---|---|
| 0:00 | Logos, JoBauko | "Good day, everyone. This is **JoBauko**, the opportunity portal of the Municipality of Bauko." |
| 0:08 | One portal, three users | "It brings jobs, scholarships, livelihood programs, and seminars and trainings into one website for all twenty-two barangays. It serves three kinds of users: residents, organizations, and the municipality." |

### For everyone

| Time | On screen | Say |
|---|---|---|
| 0:18 | Browse | "Anyone can browse without an account. Search, then filter by barangay, organization, education, skill, salary or deadline. Every listing shows the details that matter for its kind. *[And signed-in users can report a listing that looks wrong.]*" |

### For residents

| Time | On screen | Say |
|---|---|---|
| 0:30 | Sign up | "Residents sign up with their barangay and current status, and verify their email and their mobile number." |
| 0:40 | Profile | "They fill in one profile: education, work experience, trainings, certifications, skills and interests. It is done once and used for every application." |
| 0:52 | Resume | "They can upload a resume, which JoBauko reads for them to check and confirm, or build a clean one-page resume on the site. Birth date and sex are left out unless they choose to include them." |
| 1:02 | Matching | "JoBauko then recommends opportunities that fit, and always explains why, including what they can add to their profile to improve a match. *[They can still browse and apply to anything.]*" |
| 1:12 | Apply and track | "Applying takes a few clicks. Applicants follow every step, from submitted to approved, and can save listings for later or withdraw an application." |
| 1:24 | Updates | "Updates arrive in the app, by email, or by SMS, and residents choose exactly which topics they hear about." |

### For organizations

| Time | On screen | Say |
|---|---|---|
| 1:32 | Register and verify | "Organizations register with their documents, and the municipality verifies each one before it can post." |
| 1:44 | Post | "They can post any of the four kinds of opportunity, each with its own details, required documents, slots and deadline." |
| 1:56 | Review as a team | "Then they review applicants as a team, with the match score, the profile and the resume in one place, and roles for admins, recruitment officers and reviewers." |

### For the municipality

| Time | On screen | Say |
|---|---|---|
| 2:08 | Oversight | "The municipality oversees the portal: verifying organizations, acting on reported listings, and managing accounts." |
| 2:20 | Announcements | "It can send announcements by email and SMS, and every important action is recorded in an audit log." |
| 2:30 | Analytics | "And it gets eight ready-made reports, such as applications, approved applicants and the spread across barangays, all exportable to PDF or Excel." |

### Trust and privacy

| Time | On screen | Say |
|---|---|---|
| 2:42 | Trust | "Everything follows the Data Privacy Act. SMS needs consent, uploaded files stay private, and residents can delete their account at any time." |
| 2:52 | Closing | "JoBauko: discover opportunities, build your future. Thank you." |

### Before you present

- Open https://jobauko.onrender.com a few minutes before you start, so the free server is awake if someone tries it.
- Play the video full screen with sound off. It has no audio.
- Practise once with a timer. If a line runs long, shorten it rather than speaking faster.
