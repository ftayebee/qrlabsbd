import type { QrContentMap, QrType } from '~/types/qr'
import { isValidEmail, isValidHttpUrl, isValidPhone } from './payload'

export type FieldErrors = Record<string, string>

const urlMsg = 'Enter a valid http(s) address, for example https://example.com'

function optionalUrl(errors: FieldErrors, key: string, value?: string) {
  if (value && value.trim() && !isValidHttpUrl(value)) errors[key] = urlMsg
}

/** Client-side validation mirrors the Laravel Form Requests; the server is authoritative. */
export function validateContent<T extends QrType>(type: T, content: QrContentMap[T]): FieldErrors {
  const errors: FieldErrors = {}

  switch (type) {
    case 'url': {
      const { url } = content as QrContentMap['url']
      if (!url.trim()) errors.url = 'A destination URL is required'
      else if (!isValidHttpUrl(url)) errors.url = urlMsg
      break
    }
    case 'text': {
      const { text } = content as QrContentMap['text']
      if (!text.trim()) errors.text = 'Enter the text to encode'
      else if (text.length > 1200) errors.text = 'Keep text under 1,200 characters so it stays scannable'
      break
    }
    case 'phone': {
      const { phone } = content as QrContentMap['phone']
      if (!isValidPhone(phone)) errors.phone = 'Enter a phone number with 5–15 digits, ideally with country code'
      break
    }
    case 'sms': {
      const { phone, message } = content as QrContentMap['sms']
      if (!isValidPhone(phone)) errors.phone = 'Enter a phone number with 5–15 digits'
      if (message && message.length > 300) errors.message = 'Keep the message under 300 characters'
      break
    }
    case 'email': {
      const { to, subject, body } = content as QrContentMap['email']
      const recipients = to.split(',').map(s => s.trim()).filter(Boolean)
      if (!recipients.length || !recipients.every(isValidEmail)) errors.to = 'Enter a valid email address'
      if (subject && subject.length > 200) errors.subject = 'Subject is too long'
      if (body && body.length > 600) errors.body = 'Keep the message under 600 characters'
      break
    }
    case 'wifi': {
      const { ssid, security, password } = content as QrContentMap['wifi']
      if (!ssid.trim()) errors.ssid = 'Enter the network name'
      else if (ssid.length > 32) errors.ssid = 'Network names are at most 32 characters'
      if (security === 'WPA' && (!password || password.length < 8 || password.length > 63)) errors.password = 'WPA passwords are 8–63 characters'
      if (security === 'WEP' && !password) errors.password = 'Enter the network password'
      break
    }
    case 'contact':
    case 'business_card': {
      const cc = content as QrContentMap['contact']
      if (!cc.firstName?.trim()) errors.firstName = 'First name is required'
      if (cc.email && !isValidEmail(cc.email)) errors.email = 'Enter a valid email address'
      if (cc.phone && !isValidPhone(cc.phone)) errors.phone = 'Enter a valid phone number'
      if (cc.mobile && !isValidPhone(cc.mobile)) errors.mobile = 'Enter a valid phone number'
      optionalUrl(errors, 'website', cc.website)
      if (type === 'business_card') {
        const links = (content as QrContentMap['business_card']).links ?? []
        links.forEach((l, i) => optionalUrl(errors, `links.${i}.url`, l.url))
      }
      break
    }
    case 'app': {
      const { name, iosUrl, androidUrl, fallbackUrl } = content as QrContentMap['app']
      if (!name.trim()) errors.name = 'App name is required'
      if (!iosUrl && !androidUrl && !fallbackUrl) errors.iosUrl = 'Add at least one store or fallback link'
      optionalUrl(errors, 'iosUrl', iosUrl)
      optionalUrl(errors, 'androidUrl', androidUrl)
      optionalUrl(errors, 'fallbackUrl', fallbackUrl)
      break
    }
    case 'multi_url': {
      const { title, links } = content as QrContentMap['multi_url']
      if (!title.trim()) errors.title = 'Add a page title'
      if (!links.length) errors.links = 'Add at least one link'
      links.forEach((l, i) => {
        if (!l.label.trim()) errors[`links.${i}.label`] = 'Label required'
        if (!isValidHttpUrl(l.url)) errors[`links.${i}.url`] = urlMsg
      })
      break
    }
    case 'social': {
      const { title, links } = content as QrContentMap['social']
      if (!title.trim()) errors.title = 'Add a page title'
      if (!links.length) errors.links = 'Add at least one profile'
      links.forEach((l, i) => { if (!isValidHttpUrl(l.url)) errors[`links.${i}.url`] = urlMsg })
      break
    }
    case 'pdf': {
      const { title, fileId } = content as QrContentMap['pdf']
      if (!title.trim()) errors.title = 'Add a document title'
      if (!fileId) errors.file = 'Upload a PDF file'
      break
    }
  }
  return errors
}
