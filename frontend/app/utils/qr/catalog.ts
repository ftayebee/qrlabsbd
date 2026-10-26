import type { QrContentMap, QrMode, QrType } from '~/types/qr'

export interface QrTypeMeta {
  type: QrType
  slug: string
  label: string
  short: string
  icon: string
  modes: QrMode[]
  /** Hosted types always resolve to a page on our domain and need an account. */
  hosted: boolean
  seoTitle: string
  seoDescription: string
  intro: string
  howItWorks: string[]
  tips: string[]
}

export const QR_CATALOG: Record<QrType, QrTypeMeta> = {
  url: {
    type: 'url', slug: 'url', label: 'Website URL', short: 'Link to any web page', icon: 'Link2',
    modes: ['static', 'dynamic'], hosted: false,
    seoTitle: 'URL QR Code Generator – Static or Editable Links',
    seoDescription: 'Turn any web address into a QR code. Choose a static code that encodes the link directly, or a dynamic code you can redirect and track after printing.',
    intro: 'A URL QR code opens a web page the moment it is scanned. Static codes store the address itself; dynamic codes store a short link on our domain so you can change the destination later without reprinting.',
    howItWorks: ['Paste the full address of the page you want to open.', 'Pick static for a permanent link, or dynamic to edit and track it later.', 'Style the code, test it with your phone, then download PNG, SVG or PDF.'],
    tips: ['Use HTTPS destinations so phones do not show security warnings.', 'Dynamic codes stay short, which keeps the symbol less dense and easier to scan.', 'Add UTM parameters to the destination if you also track campaigns in your web analytics.'],
  },
  pdf: {
    type: 'pdf', slug: 'pdf', label: 'PDF', short: 'Share a hosted document', icon: 'FileText',
    modes: ['dynamic'], hosted: true,
    seoTitle: 'PDF QR Code Generator – Share Menus, Brochures and Manuals',
    seoDescription: 'Upload a PDF and get a QR code that opens it on any phone. Replace the file later without changing the printed code.',
    intro: 'A QR code cannot hold a whole document, so we host your PDF securely and the code points to a fast viewer page. Upload a new version at any time and the same code shows the new file.',
    howItWorks: ['Upload a PDF up to your plan’s file size limit.', 'We store it with a randomised name and create a hosted viewer page.', 'Print the QR code. Replace the file later if the content changes.'],
    tips: ['Compress large PDFs so they open quickly on mobile data.', 'Use a descriptive title; it appears on the viewer page.', 'Menus and price lists are ideal: update them without reprinting table cards.'],
  },
  multi_url: {
    type: 'multi_url', slug: 'multiple-links', label: 'Multiple URLs', short: 'A page of several links', icon: 'ListTree',
    modes: ['dynamic'], hosted: true,
    seoTitle: 'Multiple Links QR Code – One Code, Many Destinations',
    seoDescription: 'Create one QR code that opens a mobile landing page with all your important links. Reorder and edit links at any time.',
    intro: 'A standard QR code holds a single value, so one code cannot open several links directly. Instead, it opens a responsive landing page we host for you, listing every link you add.',
    howItWorks: ['Add a title and as many links as you need.', 'Drag to reorder; edit or remove links whenever you like.', 'The printed code always opens the latest version of the page.'],
    tips: ['Keep labels short and action-led, such as “Book a table”.', 'Put the most important link first.', 'Use dynamic analytics to see which audiences are scanning.'],
  },
  contact: {
    type: 'contact', slug: 'vcard', label: 'Contact (vCard)', short: 'Save contact details', icon: 'Contact',
    modes: ['static', 'dynamic'], hosted: false,
    seoTitle: 'vCard QR Code Generator – Share Contact Details Instantly',
    seoDescription: 'Create a vCard QR code that saves your name, phone, email, company and address straight into a phone’s contacts.',
    intro: 'A static vCard QR code stores contact details in the standard vCard format, so phones can add them to contacts without an internet connection. A dynamic version opens a hosted contact page you can keep up to date.',
    howItWorks: ['Fill in the details you want to share.', 'Choose static for offline saving, or dynamic for an editable hosted page.', 'Download and add it to business cards, badges or email signatures.'],
    tips: ['Static vCards get dense quickly; include only the fields you need.', 'Use international phone format, for example +44 20 7946 0000.', 'Choose dynamic if your role or number is likely to change.'],
  },
  text: {
    type: 'text', slug: 'text', label: 'Plain text', short: 'Display a short message', icon: 'Type',
    modes: ['static'], hosted: false,
    seoTitle: 'Text QR Code Generator – Encode Any Message',
    seoDescription: 'Encode plain text into a QR code that works offline. Ideal for serial numbers, instructions and short notes.',
    intro: 'A text QR code stores your message directly in the symbol. It works without internet access and never touches our servers unless you choose to save it.',
    howItWorks: ['Type or paste your text.', 'Keep it short for a less dense, more scannable code.', 'Download and print.'],
    tips: ['Under 300 characters scans reliably on most phones.', 'Text codes are static: the content cannot change after printing.', 'Use a URL code instead if you need tracking.'],
  },
  app: {
    type: 'app', slug: 'app-store', label: 'App download', short: 'App Store & Google Play', icon: 'Smartphone',
    modes: ['static', 'dynamic'], hosted: false,
    seoTitle: 'App Store QR Code – Send Users to iOS or Google Play',
    seoDescription: 'Create one QR code for your app. Dynamic codes detect iPhone or Android and send each visitor to the right store.',
    intro: 'A dynamic app QR code checks the scanning device and redirects to the App Store or Google Play, with a hosted page for desktop visitors. A static code can only point to one store link.',
    howItWorks: ['Add your App Store and Google Play listing URLs.', 'Optionally add a fallback website for other devices.', 'We route each scan to the matching store.'],
    tips: ['Opening the app directly requires universal links or app links configured in your app; store routing works without it.', 'Test on both an iPhone and an Android phone before printing.', 'Use the fallback URL for a landing page explaining the app.'],
  },
  sms: {
    type: 'sms', slug: 'sms', label: 'SMS', short: 'Pre-filled text message', icon: 'MessageSquare',
    modes: ['static'], hosted: false,
    seoTitle: 'SMS QR Code Generator – Pre-filled Text Messages',
    seoDescription: 'Create a QR code that opens a pre-written SMS to your number. Great for opt-ins, feedback and support.',
    intro: 'An SMS QR code opens the messaging app with your number and an optional message already filled in. The person only has to press send.',
    howItWorks: ['Enter the phone number in international format.', 'Add an optional pre-filled message.', 'Download and print.'],
    tips: ['Keep the message short and clear about what happens next.', 'Some phones ignore pre-filled text; the number is always used.', 'Mention standard messaging rates where required.'],
  },
  email: {
    type: 'email', slug: 'email', label: 'Email', short: 'Compose an email', icon: 'Mail',
    modes: ['static'], hosted: false,
    seoTitle: 'Email QR Code Generator – Pre-filled Subject and Body',
    seoDescription: 'Create a QR code that opens a new email with recipient, subject and message already filled in.',
    intro: 'An email QR code uses a standard mailto link. Scanning it opens the default mail app with the recipient, subject and body ready to send.',
    howItWorks: ['Enter the recipient address.', 'Add an optional subject and message.', 'Download the code; special characters are encoded automatically.'],
    tips: ['Use a shared inbox so messages are not missed.', 'Put a reference such as an order number in the subject.', 'Long bodies make dense codes; keep it brief.'],
  },
  phone: {
    type: 'phone', slug: 'phone', label: 'Phone call', short: 'Start a call', icon: 'Phone',
    modes: ['static'], hosted: false,
    seoTitle: 'Phone Number QR Code – Tap to Call',
    seoDescription: 'Generate a QR code that dials your number. Ideal for signage, vehicles and support materials.',
    intro: 'A phone QR code stores a tel: link. Scanning it shows the number with a prompt to call, so nobody has to type it in.',
    howItWorks: ['Enter your number in international format.', 'Preview and style the code.', 'Download and print.'],
    tips: ['Include the country code so it works for visitors.', 'The phone always asks before dialling.', 'Pair it with your opening hours on the printed material.'],
  },
  social: {
    type: 'social', slug: 'social-media', label: 'Social media', short: 'All your profiles', icon: 'AtSign',
    modes: ['dynamic'], hosted: true,
    seoTitle: 'Social Media QR Code – One Code for Every Profile',
    seoDescription: 'Create a branded page with all your social profiles behind a single QR code. Update links any time.',
    intro: 'A social media QR code opens a branded page listing your profiles, from Instagram and TikTok to LinkedIn and WhatsApp. Add or remove networks without reprinting.',
    howItWorks: ['Choose your networks and paste profile links.', 'Add a title and short description.', 'Share one code across packaging, posters and receipts.'],
    tips: ['Lead with the network your audience uses most.', 'Use full profile URLs, not just handles.', 'Check analytics to see where scans come from.'],
  },
  business_card: {
    type: 'business_card', slug: 'business-card', label: 'Business card', short: 'Digital profile page', icon: 'IdCard',
    modes: ['dynamic'], hosted: true,
    seoTitle: 'Digital Business Card QR Code – Share a Profile Page',
    seoDescription: 'Create a digital business card with your photo-free profile, links and a one-tap “Save contact” button behind a QR code.',
    intro: 'A business card QR code opens a polished profile page with your role, contact options and links, plus a button to download your details as a vCard.',
    howItWorks: ['Add your details, headline and links.', 'Pick an accent colour that matches your brand.', 'Print it on cards, badges and email signatures.'],
    tips: ['Keep the headline to one line.', 'Edit the page whenever you change roles; the code stays the same.', 'Use one code per person for accurate analytics.'],
  },
  wifi: {
    type: 'wifi', slug: 'wifi', label: 'Wi-Fi', short: 'Join a network', icon: 'Wifi',
    modes: ['static'], hosted: false,
    seoTitle: 'Wi-Fi QR Code Generator – Join Without Typing a Password',
    seoDescription: 'Create a Wi-Fi QR code that connects phones to your network instantly. Generated in your browser; we never log your password.',
    intro: 'A Wi-Fi QR code stores the network name, security type and password in the standard format phones understand. It is generated in your browser and the password is never sent to analytics or logs.',
    howItWorks: ['Enter the network name (SSID) exactly as it appears.', 'Choose the security type and enter the password.', 'Print and place it where guests can scan it.'],
    tips: ['Use a separate guest network rather than your main one.', 'If you change the password, you need a new code.', 'Special characters are escaped automatically.'],
  },
}

export const QR_TYPE_LIST = Object.values(QR_CATALOG)

export function metaBySlug(slug: string): QrTypeMeta | undefined {
  return QR_TYPE_LIST.find(m => m.slug === slug)
}

export function defaultContent<T extends QrType>(type: T): QrContentMap[T] {
  const defaults: QrContentMap = {
    url: { url: '' },
    pdf: { title: '' },
    multi_url: { title: '', links: [{ label: '', url: '' }] },
    contact: { firstName: '' },
    text: { text: '' },
    app: { name: '' },
    sms: { phone: '', message: '' },
    email: { to: '', subject: '', body: '' },
    phone: { phone: '' },
    social: { title: '', links: [{ platform: 'instagram', url: '' }] },
    business_card: { firstName: '', links: [] },
    wifi: { ssid: '', security: 'WPA', password: '', hidden: false },
  }
  return structuredClone(defaults[type])
}
