import type { QrType } from '~/types/qr'

export interface UseCase {
  slug: string
  title: string
  icon: 'UtensilsCrossed' | 'Store' | 'ShoppingBag' | 'Ticket' | 'Megaphone' | 'GraduationCap' | 'Briefcase'
  summary: string
  seoTitle: string
  seoDescription: string
  types: QrType[]
  ideas: { title: string; body: string }[]
  tips: string[]
}

export const USE_CASES: UseCase[] = [
  {
    slug: 'restaurants', title: 'Restaurants & cafés', icon: 'UtensilsCrossed',
    summary: 'Menus that update without reprinting, guest Wi-Fi, reviews and reservations.',
    seoTitle: 'QR Codes for Restaurants – Menus, Wi-Fi and Reviews',
    seoDescription: 'Use dynamic QR codes for restaurant menus you can update any time, guest Wi-Fi access, table ordering links and review requests.',
    types: ['pdf', 'url', 'wifi', 'multi_url'],
    ideas: [
      { title: 'Table menus', body: 'Host your menu as a PDF or web page. Change prices or daily specials and every table card updates.' },
      { title: 'Guest Wi-Fi', body: 'A Wi-Fi code on the counter lets guests connect without asking for the password.' },
      { title: 'Reviews and bookings', body: 'A multiple-links page can offer reviews, reservations and delivery in one scan.' },
    ],
    tips: ['Print codes at least 2.5 cm wide for table cards.', 'Use a frame label such as “Scan for menu” so guests know what to expect.', 'Compare scans by location with campaigns.'],
  },
  {
    slug: 'retail', title: 'Retail stores', icon: 'Store',
    summary: 'Product details, promotions and loyalty sign-ups from shelf and window.',
    seoTitle: 'QR Codes for Retail – Shelf Labels, Promotions and Loyalty',
    seoDescription: 'Connect in-store shoppers to product pages, promotions and loyalty programmes with QR codes you can update and measure.',
    types: ['url', 'multi_url', 'social', 'sms'],
    ideas: [
      { title: 'Shelf talkers', body: 'Link to reviews, sizing guides or video demos right where the decision happens.' },
      { title: 'Window displays', body: 'Capture interest after hours with a code that opens your current promotion.' },
      { title: 'Loyalty opt-in', body: 'An SMS code pre-fills a keyword so joining takes one tap.' },
    ],
    tips: ['Rotate the destination of window codes with each campaign.', 'Check device breakdowns to optimise the landing page for mobile.', 'Keep codes away from glare on glossy surfaces.'],
  },
  {
    slug: 'ecommerce', title: 'Ecommerce & packaging', icon: 'ShoppingBag',
    summary: 'Turn packaging into a channel for reorders, setup guides and social follows.',
    seoTitle: 'QR Codes on Packaging – Reorders, Manuals and Social',
    seoDescription: 'Put dynamic QR codes on packaging and inserts to drive reorders, share setup guides and grow social followings.',
    types: ['url', 'pdf', 'social', 'app'],
    ideas: [
      { title: 'Package inserts', body: 'Send customers to setup guides, warranty registration or a reorder page.' },
      { title: 'Manuals as PDF', body: 'Host manuals as PDFs and replace them when the product changes.' },
      { title: 'App downloads', body: 'One code routes iPhone and Android customers to the right store.' },
    ],
    tips: ['Use dynamic codes on packaging: print runs outlive URLs.', 'Use high error correction on curved or textured packaging.', 'Test on the final material before a large run.'],
  },
  {
    slug: 'events', title: 'Events & venues', icon: 'Ticket',
    summary: 'Schedules, maps, networking and feedback, all from one code on a badge or poster.',
    seoTitle: 'QR Codes for Events – Schedules, Networking and Feedback',
    seoDescription: 'Share event schedules, venue maps, speaker contacts and feedback forms with QR codes that you can update during the event.',
    types: ['multi_url', 'business_card', 'pdf', 'wifi'],
    ideas: [
      { title: 'Event hub', body: 'A links page with the agenda, map and live updates, editable mid-event.' },
      { title: 'Speaker cards', body: 'Digital business cards let attendees save contacts in one tap.' },
      { title: 'Feedback', body: 'Swap the destination to the survey link as sessions end.' },
    ],
    tips: ['Put the code at eye level on posters.', 'Use a large quiet zone on busy backgrounds.', 'Pause codes after the event to avoid stale links.'],
  },
  {
    slug: 'marketing', title: 'Marketing campaigns', icon: 'Megaphone',
    summary: 'Bridge print and digital, and compare which placement drives scans.',
    seoTitle: 'QR Codes for Marketing – Track Print Campaigns',
    seoDescription: 'Measure print, outdoor and direct-mail campaigns with dynamic QR codes, campaign grouping and scan analytics.',
    types: ['url', 'social', 'multi_url', 'email'],
    ideas: [
      { title: 'Placement testing', body: 'Create one code per placement and group them in a campaign to compare.' },
      { title: 'Direct mail', body: 'Send recipients to a personalised landing page and measure response.' },
      { title: 'Social growth', body: 'A social page lists every profile behind one code.' },
    ],
    tips: ['Name codes after their placement for clean reports.', 'Export CSVs to combine with your web analytics.', 'Add UTM tags to destinations.'],
  },
  {
    slug: 'education', title: 'Education', icon: 'GraduationCap',
    summary: 'Course materials, enrolment links and campus Wi-Fi for students and staff.',
    seoTitle: 'QR Codes for Schools and Universities',
    seoDescription: 'Share course materials, enrolment forms and campus information with QR codes that are easy to update each term.',
    types: ['pdf', 'url', 'wifi', 'multi_url'],
    ideas: [
      { title: 'Handouts', body: 'Link worksheets and reading lists; update them each term without reprinting.' },
      { title: 'Open days', body: 'A links page with the map, timetable and application form.' },
      { title: 'Campus Wi-Fi', body: 'Guest network codes for visitors and conference rooms.' },
    ],
    tips: ['Keep student-facing pages mobile-friendly.', 'Use folders to organise codes by department.', 'Archive codes at the end of each term.'],
  },
  {
    slug: 'professional-services', title: 'Professional services', icon: 'Briefcase',
    summary: 'Digital business cards, booking links and document sharing for client work.',
    seoTitle: 'QR Codes for Professional Services and Consultants',
    seoDescription: 'Share digital business cards, booking links and client documents with professional, branded QR codes.',
    types: ['business_card', 'contact', 'url', 'pdf'],
    ideas: [
      { title: 'Business cards', body: 'A profile page with a one-tap “Save contact” button that stays current.' },
      { title: 'Bookings', body: 'Link straight to your scheduling page from proposals and signage.' },
      { title: 'Brochures', body: 'Share service brochures as hosted PDFs.' },
    ],
    tips: ['Use your brand colours and logo for recognition.', 'Choose dynamic vCards if your details change.', 'Add the code to email signatures and slides.'],
  },
]

export function useCaseBySlug(slug: string): UseCase | undefined {
  return USE_CASES.find(u => u.slug === slug)
}
