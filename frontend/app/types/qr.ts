export const QR_TYPES = [
  'url',
  'pdf',
  'multi_url',
  'contact',
  'text',
  'app',
  'sms',
  'email',
  'phone',
  'social',
  'business_card',
  'wifi',
] as const

export type QrType = (typeof QR_TYPES)[number]
export type QrMode = 'static' | 'dynamic'
export type QrStatus = 'active' | 'paused' | 'archived'

export interface UrlContent { url: string }
export interface TextContent { text: string }
export interface PhoneContent { phone: string }
export interface SmsContent { phone: string; message?: string }
export interface EmailContent { to: string; subject?: string; body?: string }
export type WifiSecurity = 'WPA' | 'WEP' | 'nopass'
export interface WifiContent { ssid: string; security: WifiSecurity; password?: string; hidden?: boolean }

export interface ContactContent {
  firstName: string
  lastName?: string
  company?: string
  jobTitle?: string
  phone?: string
  mobile?: string
  email?: string
  website?: string
  street?: string
  city?: string
  region?: string
  postcode?: string
  country?: string
  note?: string
}

export interface BusinessCardContent extends ContactContent {
  headline?: string
  bio?: string
  accentColor?: string
  links?: LinkItem[]
}

export interface LinkItem { label: string; url: string }

export interface MultiUrlContent { title: string; description?: string; links: LinkItem[] }

export type SocialPlatform =
  | 'instagram' | 'facebook' | 'x' | 'linkedin' | 'tiktok' | 'youtube'
  | 'github' | 'whatsapp' | 'telegram' | 'pinterest' | 'threads' | 'website'

export interface SocialLink { platform: SocialPlatform; url: string }
export interface SocialContent { title: string; description?: string; links: SocialLink[] }

export interface AppContent { name: string; iosUrl?: string; androidUrl?: string; fallbackUrl?: string; description?: string }

export interface PdfContent { title: string; fileId?: number; fileName?: string; description?: string }

export interface QrContentMap {
  url: UrlContent
  pdf: PdfContent
  multi_url: MultiUrlContent
  contact: ContactContent
  text: TextContent
  app: AppContent
  sms: SmsContent
  email: EmailContent
  phone: PhoneContent
  social: SocialContent
  business_card: BusinessCardContent
  wifi: WifiContent
}

export type QrContent = QrContentMap[QrType]

export type DotStyle = 'square' | 'rounded' | 'dots' | 'classy' | 'classy-rounded' | 'extra-rounded'
export type CornerSquareStyle = 'square' | 'dot' | 'extra-rounded'
export type CornerDotStyle = 'square' | 'dot'
export type ErrorCorrection = 'L' | 'M' | 'Q' | 'H'
export type FrameStyle = 'none' | 'label-bottom' | 'label-top' | 'badge'

export interface QrDesign {
  foreground: string
  background: string
  transparent: boolean
  errorCorrection: ErrorCorrection
  dotStyle: DotStyle
  cornerSquareStyle: CornerSquareStyle
  cornerDotStyle: CornerDotStyle
  cornerColor?: string
  logoUrl?: string | null
  /** Uploaded logo file; the server derives logoUrl from it. */
  logoFileId?: number | null
  logoSize: number
  margin: number
  frame: FrameStyle
  frameLabel: string
  frameColor: string
}

export const DEFAULT_DESIGN: QrDesign = {
  foreground: '#14251A',
  background: '#FFFFFF',
  transparent: false,
  errorCorrection: 'M',
  dotStyle: 'rounded',
  cornerSquareStyle: 'extra-rounded',
  cornerDotStyle: 'dot',
  cornerColor: '#1DB45A',
  logoUrl: null,
  logoSize: 0.22,
  margin: 12,
  frame: 'none',
  frameLabel: 'Scan me',
  frameColor: '#14251A',
}

export interface QrCodeRecord {
  id: number
  name: string
  type: QrType
  mode: QrMode
  status: QrStatus
  shortCode: string | null
  shortUrl: string | null
  encodedValue: string
  content: Record<string, unknown>
  design: QrDesign
  folderId: number | null
  campaignId: number | null
  campaign?: { id: number; name: string; color: string | null } | null
  folder?: { id: number; name: string } | null
  totalScans: number
  uniqueScans: number
  lastScannedAt: string | null
  canEdit: boolean
  readOnlyReason?: string | null
  moderationStatus?: string
  createdAt: string
  updatedAt: string
}
