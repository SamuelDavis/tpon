document.addEventListener('DOMContentLoaded', function () {
  function onNoteChange (e) {
    const container = e.target.parentNode
    const id = container.getAttribute('id')
    const value = e.target.value.trim()
    if (!value) {
      container.parentNode.removeChild(container)
      localStorage.removeItem(id)
    } else {
      localStorage.setItem(id, value)
    }
  }

  function injectNoteUI (from, to) {
    const id = `${from}-${to}`
    let input = document.querySelector(`#${id} > input`)
    if (!input) {
      const container = document.createElement('div')
      container.setAttribute('id', id)
      const label = document.createElement('small')
      label.textContent = [...new Set([from, to].map((id) => id.slice(1).split('_').shift()))].join(' - ')
      input = document.createElement('textarea')
      input.value = localStorage.getItem(id)
      input.addEventListener('blur', onNoteChange)
      input.addEventListener('keyup', onNoteChange)

      container.appendChild(label)
      container.appendChild(input)

      const toNode = document.getElementById(to)
      let sibling = toNode.nextSibling
      while (sibling && sibling.tagName !== 'SPAN')
        sibling = sibling.nextSibling
      toNode.parentNode.insertBefore(container, sibling)
    }

    input.focus()
  }

  function quote () {
    const selection = window.getSelection()
    const range = selection.rangeCount ? selection.getRangeAt(0) : document.createRange()

    let from = null
    let to = null
    const spans = document.getElementsByTagName('span')
    for (let i = 0; i < spans.length; i++) {
      if (range.intersectsNode(spans[i]))
        to = spans[i]
      if (from === null)
        from = to
      if (spans[i] === selection.focusNode)
        break
    }

    if (from && to)
      injectNoteUI(from.getAttribute('id'), to.getAttribute('id'))
  }

  function injectFromLocalStorage () {
    for (let id in localStorage) {
      const [from, to] = id.split('-')
      if (document.getElementById(from) && document.getElementById(to))
        injectNoteUI(from, to)
    }
  }

  window.addEventListener('mouseup', quote)

  injectFromLocalStorage()

  const importBtn = document.createElement('button')
  importBtn.textContent = 'Import Notes'
  importBtn.addEventListener('click', (e) => {
    const notes = JSON.parse(prompt('Notes JSON', '{}'))
    if (Object.keys(notes).length)
      localStorage.clear()
    for (let key in notes) {
      if (notes.hasOwnProperty(key))
        localStorage.setItem(key, notes[key])
    }
    injectFromLocalStorage()
  })
  document.body.prepend(importBtn)

  const exportBtn = document.createElement('button')
  exportBtn.textContent = 'Export Notes'
  exportBtn.addEventListener('click', (e) => {
    const text = JSON.stringify(localStorage)
    const textArea = document.createElement('textarea')
    textArea.value = text
    document.body.appendChild(textArea)
    textArea.select()

    try {
      let successful = document.execCommand('copy')
      if (!successful) {
        throw new Error(`Copying text was unsuccessful`)
      }
    } catch (err) {
      console.error('Fallback: Oops, unable to copy', err)
    }

    document.body.removeChild(textArea)
  })
  document.body.prepend(exportBtn)
})
